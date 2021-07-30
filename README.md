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
*	Cache Method and Lifetime are applied to each cached media  
*	Uses Javascript friendly timestamps for caching (ISO 8601).  


## Adding to your Project  
  
Install the composer package:  

```php
composer require FreshVine/ExpiringMediaCache
```

The library will use a default directory and path for the cache unless you define a different one. The default directory is within the same directory as the library in a folder called `/media-cache/`. The library will also attempt to define the public URL to access this directory. It will only be successful in simple and direct server configurations (no advanced apache/ngnix mapping setups).

  
## Example Usage  

```php

require_once __DIR__ . '/vendor/autoload.php';

// Create an instance of the Expiring Media Cache
$ExpiringMedia = new \FreshVine\ExpiringMediaCache\ExpiringMediaCache();

// Get a local URL for cached media
$Cached = $ExpiringMedia->cacheThis('https://github.com/FreshVine/expiring-media-cache/raw/main/test/images/');
$URL = $ExpiringMedia->getURL( $Cached );


// Expire media which has outlived its 
$ExpiringMedia->cleanUp();
```




## Configuration Options

### CacheMethod  
The method of caching defines how to apply the lifetime to a cached item. When set to 'first' the lifetime will be measured from when the item was first cached. When set to 'request' it will be measured against when it was last requested from the cache (not from the filesystem). Note that this is stored with each cached item.

**Options:** first, request  
**Default:** first  

		$ExpiringMedia->setCacheMethod( $cacheMethod );
		$ExpiringMedia->getCacheMethod( );

### Lifetime  
The lifetime is the period of time in minutes that an item should remain in the cache.   

**Default:** 10080 Minutes (7 Days)  

	$ExpiringMedia->setLifetime( 7 * 24 * 60 );
	$ExpiringMedia->getLifetime();

### LocalMediaPath  
This allows you to define the location that the cache should exist. This directory must only be used for the media cache, as the cache will remove any unexpected files from the directory. When you define this path using the supplied local file system controller it will attempt to determine the public URL as well.  

**Default:** `__DIR__ . '/media-cache/'`  

	$ExpiringMedia->setLocalMediaPath( __DIR__ . '/media-cache/' );
	$ExpiringMedia->getLocalMediaPath( );


### LocalPublicURL  
Related to the LocalMediaPath - this is the public URL to the supplied media path.  

**Default:** `YOURDOMAIN/vendor/freshvine/expiring-media-cache/media-cache/`  

	$ExpiringMedia->setLocalPublicURL( 'http://localhost:8080/media-cache/' );
	$ExpiringMedia->getLocalPublicURL( );


### FileController  
This library includes a file controller written for the local filesystem that PHP is installed on. You may need to extend the abstracted functions in the File controller to support a different storage location (like a CDN or other). You can use the `Controllers\FileLocal.php` to help you.  
  
**Default:** `FreshVine\ExpiringMediaCache\Controllers\FileLocal`  

	$MyFileController = new FreshVine\ExpiringMediaCache\Controllers\FileLocal( $ExpiringMedia );
	$ExpiringMedia->setFileController( $MyFileController );
