# Easy Cacheable Models for Laravel (for ```file``` driver)

## Introduction
This is a simple solution to streamline using the Cache facade with Eloquent models and their relationships, primarily with the limitations of the default ```file``` driver. It provides methods to easily generate, retrieve and flush cached instances of Models in addition to any relationships in their model file specified via a function. Cache for a given model is automatically flushed upon any changes along with any cached relationships.

This was created for an earlier project, so that we could easily implement caching in our preestablished systems. As such, you can either use it as reference for your own software or improve on it.

All methods have appropriate documentation.

**Note: Only supports caching of relationships that use an Eloquent Model.**

## Install via Composer (WIP):
In your Laravel project's root directory, run: ```composer require lewis-neiland/laravel-cacheable-model```. This will install the needed files to your project.

## Getting Started:
### Importing
In any Eloquent Model file you wish to use this for, add the following as seen here:
```
use CacheableModel;

class Model
{
    use CacheableModel;
}
```
