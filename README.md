# Expiring Media Cache in PHP
[![Build Status](https://api.travis-ci.com/FreshVine/expiring-media-cache.svg)](https://app.travis-ci.com/github/FreshVine/expiring-media-cache)
[![Total Downloads](https://poser.pugx.org/freshvine/expiringmediacache/d/total.svg)](https://packagist.org/packages/freshvine/expiringmediacache)
[![Version](https://poser.pugx.org/freshvine/expiringmediacache/v/stable.svg)](https://packagist.org/packages/freshvine/expiringmediacache)
[![License](https://poser.pugx.org/freshvine/expiringmediacache/license.svg)](https://packagist.org/packages/freshvine/expiringmediacache)  
  
A simple way to cache media locally and remove it once it has expired.  
  
The core concept of this library is simple. It creates local to you copies of accessible remote media. At the same time it creates and maintains an index of all the cached media. After a pre-determined period of time the local copies of media will expire, and be removed from your file system, and the index.  
  
This is ideal for projects where you want to include temporary content from third party sources, but do not want to build up a large repository of old and irrelevant content you won't use in the future.  
  
Since PHP is a single threaded (and thus a blocking language) you will likely want to process these media requests separately from your primary page load (via an XHR request on DOM Ready for example). This will greatly improve your load performance. However you can choose to implement this library in any way you see fit.  
  
## Features  

*	Easily create a local repository of media.  
*	Remove old media when no longer used.  
*	Quickly convert remote URLs into local URLs when already cached.  
*	Easy to integrate into your projects.  
*	Cache Method and Lifetime are applied individually to cached media.  
*	Simplify the management of temporal media content.
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
$Cached = $ExpiringMedia->cacheThis('https://github.com/FreshVine/expiring-media-cache/raw/main/test/images/rodin-thinker.jpg');
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

### CleanupOnDestruct  
If you are not caching many files you can opt to cleanup the cache everytime the class is loaded. This can be expensive as it requires the processing of every cached entry. By default this is disabled and you need to manually call `$ExpiringMedia->cleanUp();` to remove entries that have expired, or whose media were otherwise removed.

**Options:** Boolean
**Default:** false

	$ExpiringMedia->setCleanupOnDestruct( true );
	$ExpiringMedia->getCleanupOnDestruct();

### Lifetime  
The lifetime is the period of time in minutes that an item should remain in the cache.   

**Default:** 10080 Minutes (7 Days)  

	$ExpiringMedia->setLifetime( 7 * 24 * 60 );
	$ExpiringMedia->getLifetime();

### LocalPath  
This allows you to define the location that the cache should exist. This directory must only be used for the media cache, as the cache will remove any unexpected files from the directory. When you define this path using the supplied local file system controller it will attempt to determine the public URL as well.  

**Default:** `__DIR__ . '/media-cache/'`  

	$ExpiringMedia->setLocalPath( __DIR__ . '/media-cache/' );
	$ExpiringMedia->setLocalPath( );


### LocalPublicURL  
Related to the LocalMediaPath - this is the public URL to the supplied media path.  

**Default:** `YOURDOMAIN/vendor/freshvine/expiring-media-cache/media-cache/`  

	$ExpiringMedia->setLocalPublicURL( 'http://localhost:8080/media-cache/' );
	$ExpiringMedia->getLocalPublicURL( );

### WriteEveryChange
Every time the ExpiringMediaCache is unset the cache writing is triggered. If you want to write the cache whenever a change is made you can do so my enabling this. Note that if you are storying a lot of media this will have some level of performance penalty.  
  
**Default:** false  

	$ExpiringMedia->setWriteEveryChange( true );
	$ExpiringMedia->getWriteEveryChange( );

### FileController  
This library includes a file controller written for the local filesystem that PHP is installed on. You may need to extend the abstracted functions in the File controller to support a different storage location (like a CDN or other). You can use the `Controllers\FileLocal.php` to help you.  
  
**Default:** `FreshVine\ExpiringMediaCache\Controllers\FileLocal`  

	$MyFileController = new FreshVine\ExpiringMediaCache\Controllers\FileLocal( $ExpiringMedia );
	$ExpiringMedia->setFileController( $MyFileController );



## Function Reference


### cacheThis  
This is the primary caching function. It takes a remote URL that you would like to cache as it's primary attribute. You can add an optional Prefix and/or Suffix to the filename. This is simply for your sanity when looking into the directory storing the media.  
  
**Returns** a CacheModel object  
  
`$ExpiringMedia->cacheThis( String $RemoteURL, String $FilenamePrefix = NULL, String $FilenameSuffix = NULL );`  

### cleanUp  
This function executes three clean up steps. It will remove any expired media from the cache, it will remove any cache entries whose related file no longer exists on the file system, and it will remove excess files from the directory in the file system. This runs independently from writing the cache, but is executed prior to writing the cache when the class is shutting down via its destructor.
  
**Returns** boolean  
  
`$ExpiringMedia->cleanUp();`  

### find  
This accepts a remote URL and returns the cache model if it exists.  
  
**Returns** a CacheModel object  
  
`$ExpiringMedia->find( String $RemoteURL );`  

### getURL  
You are going to want the URL from the cache. This is the function that gets that for you. To keep the cache file more manageable the URL is not stored directly in the cache, but it is generated by the ExpiringMediaCache class when requested.  
  
**Returns** a URL as a string  
  
`$ExpiringMedia->getURL( CacheModel &$CacheModel );`  
`$PublicURL = $ExpiringMedia->getURL( $ExpiringMedia->cacheThis() ); `  


### reloadCache  
If you need to unload the state of the cache and load the cache from the JSON file again you call this function. This function is called when you change the LocalPath via `setLocalPath()`.
  
**Returns** boolean  

`$ExpiringMedia->reloadCache();`  

### writeCache  
Calling this function allows you to force a writing of the cache to the `_media-cache.json` file. Even calling this function explicitly will only write the cache if there has been a change made to the cache.
  
**Returns** boolean  

`$ExpiringMedia->writeCache();`  

