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


namespace FreshVine\ExpiringMediaCache\Models;

use FreshVine\ExpiringMediaCache\ExpiringMediaCache as ExpiringMediaCache;
use FreshVine\ExpiringMediaCache\Controllers\File;
use FreshVine\ExpiringMediaCache\Models\File as FileModel;
use DateTime;
use DateTimeZone;

class Cache{
	/* -- Cache Members -- */
	public $FileModel;			// Generated
	protected $RemoteURL;		// Required
	protected $FetchURL;		// This is only needed if the media has not yet been cached. It maintains the case and GET varaibles in the URL.
	protected $CacheMethod;		// Required
	protected $Lifetime;		// Requried	- the Number of minutes the item should remain in the cache
	protected $TimestampUTC;	// Required - the current timestamp UTC
	protected $ExpiringMediaCache;	// Required for generating URLs

	protected $isCached = false;
	protected $isExpired = false;
	protected $isPermanent = false;
	protected $isRemoved = false;


	public function __construct( String $RemoteURL, Array $cacheArray = NULL, ExpiringMediaCache &$ExpiringMediaCache = NULL ){
		if( !is_null( $ExpiringMediaCache ) )
			$this->ExpiringMediaCache = $ExpiringMediaCache;

		// Start setting up the model
		$this->setRemoteURL( $RemoteURL );
		$this->setFetchURL( $RemoteURL );


		// Add the Timestamp
		if( is_array( $cacheArray ) && array_key_exists( 'Timestamp', $cacheArray ) ){
			$objDateTime =  new DateTime( $cacheArray['Timestamp'], new DateTimeZone( ExpiringMediaCache::CacheTimezone ) );
		}else{
			$objDateTime = new DateTime('NOW', new DateTimeZone( ExpiringMediaCache::CacheTimezone ) );
		}

		$this->TimestampUTC = $objDateTime->format( ExpiringMediaCache::DatetimeFormat ); //  2012-04-23T18:25:43.511Z


		// Add the Filename
		if( is_array( $cacheArray ) && array_key_exists( 'File', $cacheArray ) && array_key_exists( 'Filename', $cacheArray['File'] ) ){
			$LocalFilename = $cacheArray['File']['Filename'];
		}else{

			$LocalFilename = basename( $this->getRemoteURL() );
			if( empty( $LocalFilename ) ){
				$LocalFilename = "RANDOM";
			}
		}
		$this->FileModel = new FileModel( $LocalFilename );


		// Add the Cache Method
		if( is_array( $cacheArray ) && array_key_exists( 'CacheMethod', $cacheArray ) ){
			$this->setCacheMethod( $cacheArray['CacheMethod']  );
		}


		// Add the Lifetime
		if( is_array( $cacheArray ) && array_key_exists( 'Lifetime', $cacheArray ) ){
			$this->setLifetime( $cacheArray['Lifetime']  );
		}


		// Is this Permanent?
		if( is_array( $cacheArray ) && array_key_exists( 'isPermanent', $cacheArray ) && $cacheArray['isPermanent'] ){
			$this->setFlag( 'permanent', true );
		}


		return $this;
	}


	/**
	 * Convert this cache object into an associative array.
	 *
	 * @return array
	 */
	public function toArray(){
		if( $this->isCached === false && $this->isPermanent === false ){
			return null;	// It is not in the cache, and it is was not moved to permanent storage
		}
		if( $this->isRemoved === true ){
			return null;	// Deleted media is removed from the cache
		}


		// Okay, this item should still be in the cache
		return array( $this->RemoteURL => array(
					'File' => array(
						'Filename' => $this->FileModel->getFilename()
					),
					'isPermanent' => $this->isPermanent,
					'Timestamp' => $this->TimestampUTC,
					'Lifetime' => $this->getLifetime(),
					'CacheMethod' => $this->getCacheMethod()
				));
	}


	/**
	 * Set the timestamp for this cached media to the current time
	 *
	 * @return this
	 */
	public function makeTimestampCurrent(){
		$objDateTime = new DateTime('NOW', new DateTimeZone( ExpiringMediaCache::CacheTimezone ) );
		$this->TimestampUTC = $objDateTime->format( ExpiringMediaCache::DatetimeFormat ); //  2012-04-23T18:25:43.511Z

		return $this;
	}


	/*
	 *  Getters
	 */
	function getCacheDirectory(){
		return $this->FileModel->getCacheDirectory();
	}
	function getCacheFilename(){
		return $this->FileModel->getFilename();
	}
	function getCacheMethod(){
		return $this->CacheMethod;
	}
	function getFetchURL(){
		return $this->FetchURL;
	}
	function getLifetime(){
		return $this->Lifetime;
	}
	function getRemoteURL(){
		return $this->RemoteURL;
	}
	function getTimestamp(){
		return $this->TimestampUTC;
	}
	function isCached(){
		return $this->isCached;
	}
	function isRemoved(){
		return $this->isRemoved;
	}
	function isPermanent(){
		return $this->isPermanent;
	}


	/*
	 * Setters
	 */
	function setCacheDirectory( string $CacheDirectory ){
		// Ensure that this is a valid path
		$this->FileModel->setCacheDirectory( $CacheDirectory );

		return $this;
	}
	function setCacheMethod( string $CacheMethod ){
		if( !in_array( $CacheMethod, array('first', 'request') ) ){
			throw new \Exception('ExpiringMediaCache: Invalid value supplied for setCatchMethod. Expecting either \'first\' or \'request\'. Received: ' . $CacheMethod );
		}

		$this->CacheMethod = $CacheMethod;

		return $this;
	}
	function setCacheFilename( string $CacheFilename ){
		$this->FileModel->setFilename( $CacheFilename );

		return $this;
	}
	function setFetchURL( string $FetchURL ){
		$this->FetchURL = $FetchURL;

		return $this;
	}
	function setLifetime( int $Lifetime ){
		$this->Lifetime = $Lifetime;

		return $this;
	}
	function setRemoteURL( string $RemoteURL ){
		// Only use the domain and path without protocol or query
		$parsed = parse_url( strtolower( $RemoteURL ) );
		$this->RemoteURL = $parsed['host'] . $parsed['path'];

		return $this;
	}
	function setFlag( string $FlagType, bool $Value ){
		$FlagType = strtolower( $FlagType );
		if( !in_array( $FlagType, array('cached', 'expired', 'removed', 'permanent') ) ){
			throw new \Exception('ExpiringMediaCache: Invalid value supplied for setFlag. Expecting either \'cached\', \'expired\', \'removed\', \'permanent\'. Received: ' . $FlagType );
		}

		$FlagMap = array('cached' => 'isCached',
						'expired' => 'isExpired',
						'permanent' => 'isPermanent',
						'removed' => 'isRemoved'
		);

		$k = $FlagMap[$FlagType];

		$this->$k = $Value;

		return $this;
	}
}
?>