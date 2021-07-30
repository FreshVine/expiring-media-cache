# Expiring Media Cache in PHP
[![Build Status](https://travis-ci.com/FreshVine/expiring-media-cache.svg)](https://travis-ci.com/FreshVine/expiring-media-cache)
[![Total Downloads](https://poser.pugx.org/freshvine/expiringmediacache/d/total.svg)](https://packagist.org/packages/freshvine/expiringmediacache)
[![Version](https://poser.pugx.org/freshvine/expiringmediacache/v/stable.svg)](https://packagist.org/packages/freshvine/expiringmediacache)
[![License](https://poser.pugx.org/freshvine/expiringmediacache/license.svg)](https://packagist.org/packages/freshvine/expiringmediacache)  

A simple way to cache media locally and remove it after it expires.  

The core concept of this library is simple. It makes it simple to create local to you copies of accessible remote media. At the same time it creates and maintains an index of all the cached media. After a pre-determined period of time the local copies of media will expire, and be removed from your file system, and the index.

This is idea for projects where you want to include temporary content from third party sources, but do not want to build up a large repository of old and irrelevant content you won't use in the future. 

Since PHP is a single threaded (and thus a blocking language) you will likely want to process these media requests separately from your primary page load (via an XHR request on DOM Ready for example). This will greatly improve your load performance. However you can choose to implement this library in any way you see fit.  
  
## Features  

*	Easily create a local repository of media.
*	Remove old media when no longer used
*	Quickly convert remote URLs into local URLs when already cached.
*	Easy to integrate into your project
*	Uses Javascript friendly timestamps for caching (ISO 8601).


## Adding to your Project  
  
Install the composer package:  

```php
composer require FreshVine/ExpiringMediaCache
```

The library will use a default directory and path for the cache unless you define a different one. The default directory is within the same directory as the library in a folder called `/media-cache/`. The library will also attempt to define the public URL to access this directory. It will only be sucessful in simple and direct server configurations (no advanced apache/ngnix mapping setups).

  
## Example Usage  

```php

require_once __DIR__ . '/vendor/autoload.php';

// Create an instance of the Expiring Media Cache
$ExpiringMedia = new \FreshVine\ExpiringMediaCache\ExpiringMediaCache();

// Define the location to store the media on the server
$ExpiringMedia->setLocalMediaPath( __DIR__ . '/media-cache/' );

// Define the public URL for the local Media Path
$ExpiringMedia->setLocalMediaURL( 'http://localhost:8080/media-cache/' );

// Get a local URL for cached media
$LocalURL = $ExpiringMedia->cacheThis('https://github.com/FreshVine/expiring-media-cache/raw/main/test/images/');


// Expire media which has outlived its 
$ExpiringMedia->cleanUp();
```




## Configuration Options

