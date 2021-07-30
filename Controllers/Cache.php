<?php

/***********
 *	
 *	Expiring Media Cache
 *	https://freshvine.co/
 *	
 *	© Paul Prins
 *	https://paul.build/
 *	
 *	Licensed under MIT - For full license, view the LICENSE distributed with this source.
 *	
 ***********/

namespace FreshVine\ExpiringMediaCache\Controllers;


use FreshVine\ExpiringMediaCache\ExpiringMediaCache as ExpiringMediaCache;
use FreshVine\ExpiringMediaCache\Models\Cache as CacheModel;
use DateTime;
use DateTimeZone;

class Cache{
	
	public function __construct( ExpiringMediaCache $ExpiringMediaCache = NULL ){
		if( !is_null( $ExpiringMediaCache ) )
			$this->ExpiringMediaCache = $ExpiringMediaCache;

		return $this;
	}

	/**
	 * Instantiate the cache with the current configuration settings
	 *
	 * @return boolean
	 */
	public function instantiateCache(){
		// Set the cache file location
		$cacheFilePath = $this->getLocalPath() . '_media-cache.json';
		$this->setCacheFilePath( $cacheFilePath );

		if( !file_exists( $cacheFilePath ) ){
			// There is not cache file, so lets create one
			try{
				$this->writeCache();
			}catch(Exception $E) {
				throw $E;
			}
		}else{
			// There is a cache file, so lets try to read it
			try{
				$this->loadCache();
			}catch( Exception $E){
				throw $E;
			}
		}

		return true;
	}

	/*
	 *	Establish Members
	 */
	private		$ExpiringMediaCache;			// A passed instance of the instantiator of this class
	protected	$localPath;						// The absolute path to where the media and cache should be stored
	private		$cacheFilePath;					// The absolute path to the cache json file
	private		$cacheInBytes;
	
	protected	$lifetime = 7 * 24 * 60;		// The number of minutes before the cache should expire and remove media [default: 7 days]
	protected	$cacheMethod = 'first';			// Enum/Set: From what point in time do we use the cache. From the first request, or the most recent request. [first, request] Default is 'first'



	/*
	 *  Getters
	 */
	function getLocalPath(){
		return $this->localPath;
	}
	function getCacheFilePath(){
		return $this->cacheFilePath;
	}
	function getCacheInBytes(){
		return $this->cacheInBytes;
	}
	function getCacheMethod(){
		return $this->cacheMethod;
	}
	function getLifetime(){
		return $this->lifetime;
	}


	/*
	 * Setters
	 */
	function setlocalPath( string $localPath ){
		$this->localPath = $localPath;

		return $this;
	}
	function setCacheFilePath( string $cacheFilePath ){
		$this->cacheFilePath = $cacheFilePath;

		return $this;
	}
	function setCacheInBytes( int $cacheInBytes ){
		$this->cacheInBytes = $cacheInBytes;

		return $this;
	}
	function setCacheMethod( string $cacheMethod ){
		if( !in_array( $cacheMethod, array('first', 'request') ) ){
			throw new \Exception('ExpiringMediaCache: Invalid value supplied for setCatchMethod. Expecting either \'first\' or \'request\'. Received: ' . $cacheMethod );
		}
		$this->cacheMethod = $cacheMethod;

		return $this;
	}
	function setLifetime( int $lifetime ){
		$this->lifetime = $lifetime;	// This is in Minutes

		return $this;
	}



	/*
	 * Manage the Media Index
	 */

	/**
	 * Add a Cache model to the media index
	 *
	 * @param  string		$RemoteURL		The remote URL
	 * @param  Cache		$CacheObject	This is a Cache model for the given cache entry
	 * @return Cache Model object
	 */
	public function add( String $RemoteURL, CacheModel $CacheModel = NULL ){
		if( is_null( $CacheModel ) ){
			$CacheModel = new CacheModel( $RemoteURL, null );

			// $CacheModel->setCacheDirectory( $this->getLocalPath() );
			$CacheModel->setCacheMethod( $this->getCacheMethod() );
			$CacheModel->setLifetime( $this->getLifetime() );
			// TiemstampUTC - This is automatically generated when the Model is generated
		}


		$this->ExpiringMediaCache->addToMediaIndex( $CacheModel );

		return $CacheModel;
	}
	/*
	 * END: Manage the Media Index
	 */



	/*
	 * Manage the Cache File
	 */
	/**
	 * Check if a cached item has lived past its lifetime, and mark it as expired
	 *
	 * @param $CacheModel
	 * @return boolean
	 */
	public function checkExpired( CacheModel &$CacheModel ){
		if( $CacheModel->isPermanent() ){
			return false;	// This cannot expire
		}


		// Create the date objects for the timestamp and the current time
		$objDateTime =  new DateTime( $CacheModel->getTimestamp(), new DateTimeZone( ExpiringMediaCache::CacheTimezone ) );
		$nowDateTime = new DateTime('NOW', new DateTimeZone( ExpiringMediaCache::CacheTimezone ) );

		// Check the difference from then until now (this should always be positive)
		$Difference = $objDateTime->diff( $nowDateTime );
		$AgeInMinutes = ( $Difference->days * 24 * 60 ) + ( $Difference->h * 60 ) + $Difference->i;

		// Check if the item is still within it's lifetime
		if( $AgeInMinutes < 0 || $AgeInMinutes <= $CacheModel->getLifetime() ){
			$CacheModel->setFlag('expired', false);
			return false;
		}

		// Mark object as expired
		$CacheModel->setFlag('expired', true);

		// Add remoteURL to index to expire out
		$this->expiredIndexes[] = $CacheModel->getRemoteURL();

		return true;
	}


	/**
	 * Return the data from the current cache file
	 *
	 * @return array
	 */
	private function getCache(){
		// Cache file has already been confirmed to exist.
		$rawCacheData = $this->ExpiringMediaCache->fileRead( $this->getCacheFilePath() );
		if( empty( $rawCacheData ) ){
			throw new \Exception('ExpiringMediaCache: Attempted to load the cache but the file is empty - ' . $cacheFilePath );
		}

		// Ensure that this file is a valid JSON format
		$cachedData = json_decode( $rawCacheData, true );
		if( empty( $cachedData ) || !array_key_exists('media', $cachedData) ){
			throw new \Exception('ExpiringMediaCache: The existing cache contains invalid JSON - ' . $cacheFilePath );
		}

		return $cachedData;
	}


	/**
	 * A private function that will open an existing cache file, and load it into memory for use
	 *
	 * @return boolean
	 */
	private function loadCache(){
		$cachedData = $this->getCache();

		// Set the cache filesize
		$this->setCacheInBytes( $this->ExpiringMediaCache->fileSize( $this->getCacheFilePath() ) );


		if( !empty(  $cachedData['media'] ) ){
			foreach( $cachedData['media'] as $remoteURL => $vars ){
				// Create  the objects and load them into the
				$thisObject = new CacheModel( $remoteURL, $vars );
					$thisObject->setFlag('cached', true);

				// Check if the file exists
				if( $this->ExpiringMediaCache->exists( $thisObject->FileModel ) === false ){
					$thisObject->setFlag('removed', true);
				}

				// Check if the cache has expired for this media
				if( $this->checkExpired( $thisObject ) ){
					$thisObject->setFlag('expired', true);
				}


				$thisObject->setFlag('cached', true);
				$this->ExpiringMediaCache->addToMediaIndex( $thisObject );
			}
		}

		return true;
	}


	/**
	 * Return the Cache model for a given remote URL;
	 *
	 * @param  string		$RemoteURL	The remote URL
	 * @return Cache Model object
	 */
	public function writeCache(){
		$cacheData = array();

		// Include the current version of this library to the cache - can be used for migrating data to new versions
		$cacheData['libraryVersion'] = ExpiringMediaCache::version;

		// Include the Lifetime and Cache Method
		$cacheData['PublicURL'] = $this->ExpiringMediaCache->getLocalPublicURL();

		// Include the current write time to the cache
		$objDateTime = new DateTime('NOW', new DateTimeZone( ExpiringMediaCache::CacheTimezone ) );
		$cacheData['LastWrite'] = $objDateTime->format( ExpiringMediaCache::DatetimeFormat ); //  2012-04-23T18:25:43.511Z


		// Convert the objects into cache worthy syntax
		$cacheData['media'] = array();
		$Index = $this->ExpiringMediaCache->getMediaIndex();
		if( !empty( $Index ) ){
			foreach( $Index as $k => $cacheObject ){
				$tmp = $cacheObject->toArray();

				if( is_array( $tmp ) )
					$cacheData['media'] = $cacheData['media'] + $tmp;
			}

			ksort( $cacheData['media'] );
		}

		// Check if any of the data has updated
		if( $this->ExpiringMediaCache->fileExists( $this->getCacheFilePath() ) ){
			$previousCache = $this->getCache();

			if( $cacheData['libraryVersion'] == $previousCache['libraryVersion'] 
				&& $cacheData['PublicURL'] == $previousCache['PublicURL'] 
				&& $cacheData['media'] == $previousCache['media'] ){

				return true;	// No need to udpate the cache
			}
		}


		// Set the cache size in bytes
		$success = $this->ExpiringMediaCache->fileWrite( $this->getCacheFilePath(), json_encode( $cacheData, JSON_UNESCAPED_UNICODE ) );
		if( $success === false ){
			throw new \Exception('ExpiringMediaCache: Failed to write the cache to location: ' . $cacheFilePath );
		}

		$cacheInBytes = $this->ExpiringMediaCache->fileSize( $this->getCacheFilePath() );
		if( $cacheInBytes == false ){
			throw new \Exception('ExpiringMediaCache: Written cache file has no filesize: ' . $cacheFilePath );
		}
		$this->setCacheInBytes( $cacheInBytes );

		return true;
	}

	/*
	 * END: Manage the Cache File
	 */
}

?>