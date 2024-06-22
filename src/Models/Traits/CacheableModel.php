<?php

use DateTime;
use Exception;
use ErrorException;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Laravel Cacheable Models 
 * @version 0.1.1.1
 * Implementation to manage caching models and their relationships.
 * (Only supports caching of relationships that use an Eloquent Model).
 * @lewis-neiland
 */
trait CacheableModel{
    
    /**
     * Listens for model changes and flushes cache if so.
    */
    protected static function bootCacheableModel(){
        if (!(is_subclass_of(static::class, Model::class))) {
            die('Error: Class is not of type Illuminate\Database\Eloquent\Model');
        }

        foreach (['created', 'saved', 'deleting', 'updated'] as $event) {
            static::$event(function ($instance) use ($event) {
                if ($event === 'created' || $event === 'saved' || $event === 'updated') {
                    $instance->flushRelatedCaches($instance->getCacheableRelationships());
                }
                if ($event === 'deleting') {
                    $instance->flushRelatedCaches($instance->getCacheableRelationships());
                    $instance->flushCache();
                }
            });
        }
    }

    /**
     * Returns the model table name.
     */
    private static function getTableName()
    {
        return (new self())->getTable();
    }

    /**
     * Returns the model table name, intended to be used as the cache key.
     * @param mixed $input [optional] A value to be appended, such as an identifier for a model instance.
     * @return string @example `products`, `products_1`
    */
    private static function generateCacheKey(string|int $input = null){
        $key = static::getTableName();

        if(isset($input)){
            $key = $key.'_'.$input;
        }

        return $key;
    }

    /**
     * Returns the cache key of a model instance.
     * @param null|string|int $input [optional] A value to be appended, such as an identifier for a model relationship.
     * @return string @example `reviews_1`, `reviews_1:product`
    */
    public function cacheKey(string|int $input = null)
    {   
        $key = static::generateCacheKey($this->id);

        if(isset($input)){
            $key = $key.':'.$input;
        }

        return $key;
    }

    /**
     * Return/create a cached version of the model.
     * @param DateTime|null $time Time before the cache updates to match database. (Default - 30m)
     *
     */
    public static function getAllCached(DateTime $time = null){

        $time = $time ?? now()->addMinutes(30)->toDateTime();

        return Cache::remember(static::generateCacheKey(), $time, function () {
            return static::all();
        });
    }

    /**
    * Return/create a cached version of a Model instance.
    * @param int $id Return/create a cached version of a Model instance.
    * @param DateTime|null $time Time before the cache updates to match database. (Default - 30m)
    */
    public static function getCached(int $id, DateTime $time = null){
        $time = $time ?? now()->addMinutes(30)->toDateTime();
        $model = static::findCached($id);

        if(is_null($model)){
            $model = Cache::remember(static::generateCacheKey($id), $time, function() use ($id) {
                return static::find($id);
            });
        }

        return $model;
    }

    /**
     * Returns/creates a cached version of a relationship as an Eloquent Collection.
     * @param string $relation Name of a function that returns an Eloquent Relation. @example `product->categories()`
     * @param DateTime|null $time [optional] Time before the cache updates to match database. (Default - 30m)
    */
    public function getCachedRelation(string $relation, DateTime $time = null){
        $time = $time ?? now()->addMinutes(30)->toDateTime();
        $cachedRelation = $this->findCachedRelation($relation);

        if (is_null($cachedRelation) || isset($time)){
            $cachedRelation = $this->$relation();

            if(isset($cachedRelation)){
                $cachedRelation = Cache::remember($this->cacheKey($relation), $time, function () use ($cachedRelation){
                    return $cachedRelation->get();
                });
            }

            else{
                return null;
            }
        }

        return $cachedRelation;
    }

    /**
     * Returns a cached version of a Model instance if it is present.
     * @param string $id Id of Model instance.
    */
    public static function findCached(int $id){
        return Cache::get(static::generateCacheKey($id));
    }

    /**
     * Returns a cached version of a relationship as an Eloquent Collection if it is present.
     * @param string $relation Name of a function that returns an Eloquent Relation @example `categories`
    */
    public function findCachedRelation($relation = null){
        return Cache::get($this->cacheKey($relation));
    }

    /**
     * Flush the cached Model.
    */
    public function flushCache(){
        Cache::forget($this->cacheKey());
        return true;
    }

    /**
     * Flush the cached relation of a model instance.
     * @param string $relation The relation to forget.
     */
    public function flushCachedRelation(string $relation){
        return Cache::forget($this->cacheKey($relation));
    }

    /**
     * Flush multiple cached relations of a model instance.
     * @param array $relations The relations to flush.
     */
    public function flushCachedRelations(array $relations = null){
        if (is_null($relations)){
            $relations = $this->getCacheableRelationships();
        }

        foreach($relations as $relation){
            $this->flushCachedRelation($relation);
        }

        return true;
    }
    
    /**
     * Flush the cached relation of this model in related models.
     * @param string $relation The relation to flush.
     */
    public function flushRelatedCache(string $relation){
        $cachedModels = $this->getCachedRelation($relation);

        if (!($cachedModels->isEmpty())){
            foreach ($cachedModels as $cachedModel) {
                $cachedModel->flushCachedRelation($this->getTableName());
                $cachedModel->flushCachedRelation(lcfirst(class_basename($this)));
            }
        }

        $this->flushCachedRelation($relation);
    }

    /**
     * Flush the cached relations of this model in a related model.
     * @param array|string $relation The relations to flush.
     */
    public function flushRelatedCaches(array|string $relations = null){
        foreach ($relations as $relation){
            $this->flushRelatedCache($relation);
        }
    }

    /**
     * Returns the names of methods that define a relationship to this model and another, and the model has the cacheableModel trait.
     */
    public function getCacheableRelationships()
    {
        $model = $this;

        $relationships = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class != get_class($model) ||
                !empty($method->getParameters()) ||
                $method->getName() == __FUNCTION__) {
                continue;
            }

            try {
                $return = $method->invoke($model);

                if ($return instanceof Relation) {
                    $relatedModelClass = get_class($return->getRelated());
                    
                    if (in_array(CacheableModel::class, class_uses($relatedModelClass))) {
                        array_push($relationships, $method->getName());
                    }
                }
            } catch (ErrorException $e) {
            }
        }

        return $relationships;
    }
}