<?php

/***********
 *	
 *	ExpiringMediaCache
 *	https://freshvine.co/
 *	
 *	© Paul Prins
 *	https://paulprins.net https://paul.build/
 *	
 *	Licensed under MIT - For full license, view the LICENSE distributed with this source.
 *	
 ***********/

namespace FreshVine\ExpiringMediaCache;

use FreshVine\ExpiringMediaCache\Controllers\CacheController;
use FreshVine\ExpiringMediaCache\Controllers\FileController;
use FreshVine\ExpiringMediaCache\Controllers\FileLocalController;
use FreshVine\ExpiringMediaCache\Models\CacheModel;
use FreshVine\ExpiringMediaCache\Models\FileModel;
use DateTimeZone;

class ExpiringMediaCache{

	// Establish constants to use within the library
	const version = '1.1.0';
	const DatetimeFormat = 'Y-m-d\TH:i:s\Z';	// This is the ISO 8601 format in UTC time indicated with the Z suffix
	const CacheTimezone =  'UTC';	// The exact timezone is not important since we use offsets, but it must be constantly applied.


	function __construct( $localPath = NULL, $localPublicURL = NULL){
		$this->setFileController( new Controllers\FileLocalController( $this ) );	// The Media Controller will fetch and store remote media
		$this->CacheController = new Controllers\CacheController( $this );	// The Cache controller manages and CRUDs the cache

		// Allow for overloading the constructor values
		if( !is_null( $localPath ) ){
			$this->setLocalPath( $localPath );
			if( !is_null( $localPublicURL ) ){
				$this->setLocalPublicURL( $localPublicURL );
			}


			$this->instantiateCache();	// They did not do anything fancy here
		}else{
			// Use the default media-cache directory within the library
			$this->setLocalPath( rtrim( __DIR__, '/' ) . '/media-cache/', true );
			$this->instantiateCache();	// They did not do anything fancy here
		}

		return $this;
	}


	function __destruct() {
		if( $this->cleanupOnDestruct ){
			// Clean up expired or missing files
			$this->cleanUp();
		}

		// Ensure that we write out the cache before we shutdown
		$this->CacheController->writeCache();
	}


	/*
	 *	Primary Methods
	 */

	/**
	 * Will cache a remote media file locally, and respond with the new Cache model
	 *
	 * @param  URL		$RemoteURL			The text object provided by the source
	 * @param  String	$FilenamePrefix		Optional text to prefix the filename with in the local cache
	 * @param  String	$FilenameSuffix		Optional text to suffix the filename with in the local cache
	 * @return $CacheObject
	 */
	public function cacheThis( String $RemoteURL, String $FilenamePrefix = NULL, String $FilenameSuffix = NULL ){
		if( $this->instantiateCache() === false ){	// Ensure the cache is ready
			throw new \Exception('ExpiringMediaCache: Unable to instantiate the cache');
		}

		//
		// See if the item exists already in the cache
		try{
			$CacheObject = $this->find( $RemoteURL );

			if( $CacheObject !== false && $this->FileController->exists( $CacheObject->FileModel ))
				return $CacheObject;	// Already cached and the file exists, so no need to process further
		}catch( \Exception $E){
			echo 'Why is this not showing up';
			// This is okay. it jsut means we keep going
		}



		// Create a cache model
		$CacheObject = $this->CacheController->add( $RemoteURL );


		// Adapt the local Filename with the optional Prefix and Suffix
		$LocalFilename = $CacheObject->FileModel->getFilename();
		$FilenamePrefix = $this->FileController->cleanStringForFilename( $FilenamePrefix );
		$FilenameSuffix = $this->FileController->cleanStringForFilename( $FilenameSuffix );


		if( !is_null( $FilenamePrefix ) && !empty( $FilenamePrefix ) ){
			$LocalFilename = $FilenamePrefix .'-' . $LocalFilename;
		}

		if( !is_null( $FilenameSuffix ) && !empty( $FilenameSuffix )){
			$NameParts = pathinfo( $LocalFilename );
			$LocalFilename = $NameParts['filename'] . '-' . $FilenameSuffix . '.' . $NameParts['extension'];
		}


		// Update the local caching filename
		$CacheObject->FileModel->setFilename( $LocalFilename );
		$this->FileController->uniqueFilename( $CacheObject->FileModel );


		// Write the file
		$this->FileController->fetchRemoteMedia( $RemoteURL, $CacheObject->FileModel );	// This should return a media object
		$this->FileController->write( $CacheObject->FileModel );
		$CacheObject->setFlag('cached', true);


		// update the cache model now that the media is saved
		$this->addToMediaIndex( $CacheObject );

		// Write everytime we add to the cache
		if( $this->getWriteEveryChange() ){
			$this->CacheController->writeCache();
		}

		return $CacheObject;
	}


	/**
	 * Add or update a cache object to the index
	 *
	 * @return Boolean
	 */
	public function addToMediaIndex( CacheModel $CacheModel ){
		if( array_key_exists( $CacheModel->getRemoteURL, $this->mediaIndex ) ){
			$this->mediaIndex[$CacheModel->getRemoteURL()] = $CacheModel;
			return true;
		}

		// Ad it to the index
		$this->mediaIndex[$CacheModel->getRemoteURL()] = $CacheModel;
		ksort( $this->mediaIndex );

		return true;
	}


	/**
	 * The function will remove all expired files from the file system, and mark items for exclusion in the cache
	 *
	 * @return Boolean
	 */
	public function cleanUp(){
		if( $this->instantiateCache() === false ){	// Ensure the cache is ready
			throw new \Exception('ExpiringMediaCache: Unable to instantiate the cache');
		}

		$ExpectedFiles = array('cache' => $this->CacheController->getCacheFilename() );	// Key => Filename


		// Ensure that we load all of the media into models.
		if( !$this->CacheController->loadAllCached() ){
			return false;
		}
		

		foreach( $this->mediaIndex as $k => $CacheObject ){
			$FileShouldRemain = true;

			// Remove any mediaIndex items where their related files do not exist
			if( !$this->FileController->exists( $CacheObject->FileModel ) ){
				$CacheObject->setFlag('removed', true );
				$FileShouldRemain = false;
			}else{
				$CacheObject->setFlag('removed', false );	// cached items with a removed flag are not saved when the cache is written
			}


			// Remove any mediaIndex items that remain expired
			if( $this->CacheController->checkExpired( $CacheObject ) ){
				$CacheObject->setFlag('expired', true );
				$FileShouldRemain = false;
			}else{
				$CacheObject->setFlag('expired', false );
			}

			if( $FileShouldRemain )
				$ExpectedFiles[$k] = $CacheObject->getCacheFilename();
		}


		// Remove any files from the cache directory which are not expected to be there
		$LiveFiles = $this->FileController->listFiles();
		$ExtraFiles = array_diff( $LiveFiles, $ExpectedFiles );		// Find what should not be there
		$ExtraFiles = array_diff( $ExtraFiles, array('..', '.'));	// Ensure we don't have any weird directories

		$FlippedLive = array_flip( $ExpectedFiles );	// This will help us ensure the files are marked as removed


		if( !empty( $ExtraFiles ) ){
			foreach( $ExtraFiles as $filename ){
				$this->FileController->fileDelete( $filename );


				// Look through our index to ensure that the filx is marked as removed
				if( array_key_exists( $filename, $FlippedLive ) ){
					$Removed = $this->find( $filename );
					$Removed->setFlag('removed', true );	// Ensure this is marked
				}
			}
		}

		$this->CacheController->setLastCleanup();	// Set the last cleanup to NOW

		return true;
	}


	/**
	 * Return the Cache model for a given remote URL
	 *
	 * @param  string		$RemoteURL	The remote URL
	 * @return CacheModel object
	 */
	public function find( String $RemoteURL ){
		$RemoteURL = $this->cleanRemoteURL( $RemoteURL );
		if( !array_key_exists( $RemoteURL, $this->mediaIndex ) ){
			if( !$this->CacheController->loadCacheEntry( $RemoteURL ) ){
				return false;
			}
		}

		return $this->mediaIndex[$RemoteURL];
	}


	/**
	 * Return the public URL for a given cache model
	 *
	 * @param  string		$RemoteURL	The remote URL
	 * @return CacheModel object
	 */
	public function getURL( CacheModel &$CacheModel ){
		$BaseURL = $this->getLocalPublicURL();
		$CacheModel->getCacheFilename();

		if( $CacheModel->getCacheMethod() == 'request' ){
			// Update the TimestampUTC to current - we must ensure this is done in the mediaIndex
			$this->mediaIndex[$CacheModel->getRemoteURL()]->makeTimestampCurrent();
			$this->mediaIndex[$CacheModel->getRemoteURL()]->setFlag('expired', false);	// Ensure that it is not marked as expired


			if( $this->getWriteEveryChange() ){
				$this->CacheController->writeCache();
			}
		}

		return $BaseURL . $CacheModel->getCacheFilename();
	}


	/**
	 * Ensure that the remote URL we use for storage does not have any 
	 *
	 * @param  string		$RemoteURL	The remote URL
	 * @return string
	 */
	public function cleanRemoteURL( String $RemoteURL ){
		// Only use the domain and path without protocol or query
		$parsed = parse_url( strtolower( $RemoteURL ) );

		return $parsed['host'] . $parsed['path'];
	}


	/**
	 * Drop the current state of the cache and reload the cache from the json file
	 *
	 * @return boolean
	 */
	public function reloadCache(){
		$this->mediaIndex = array();	// Dump the mediaIndex

		return $this->CacheController->loadCache();
	}
	/*
	 *	END: Primary Methods
	 */




	/*
	 *	Establish the variables
	 */
	protected	$CacheController;
	protected	$FileController;
	protected	$cacheInstantiated = false;		// Boolean: Has the cache been instantiated yet
	protected	$writeEveryChange = false;		// Boolean: Do we write the cache once, or after every change.
	protected	$cleanupOnDestruct = false;		// Boolean: Do we run through the cleanup script everytime we destruct the class.
	protected	$mediaIndex = array();			// This is an associative array of the cache objects
	private static $instances = array();

	protected	$localPublicURL;				// This is the URL for the localMediaPath.
	protected	$prefexFilename = false;




	/*
	 *  Getters
	 */
	public function getCacheMethod(){
		$this->CacheController->getCacheMethod();
	}
	public function getLifetime(){
		return $this->CacheController->getLifetime();
	}
	public function getLocalPath(){
		return $this->CacheController->getLocalPath();
	}
	public function getLocalPublicURL(){
		return $this->localPublicURL;
	}
	public function getMediaIndex(){
		return $this->mediaIndex;
	}
	public function getWriteEveryChange(){
		return $this->WriteEveryChange;
	}
	public function getCleanupOnDestruct(){
		return $this->cleanupOnDestruct;
	}
	public function getUserAgent(){
		return $this->FileController->getUserAgent();
	}
	/*
	 *  END: Getters
	 */


	/*
	 * Setters
	 */
	public function setCacheMethod( string $cacheMethod ){
		try{
			$this->CacheController->setCacheMethod( $cacheMethod );
		}catch( \Exception $E){
			throw $E;
		}

		return $this;
	}

	public function setFileController( Controllers\FileController $FileController ){
		$this->FileController = $FileController;

		return $this;
	}

	public function setLifetime( int $Lifetime ){
		$this->CacheController->setLifetime( $Lifetime );

		return $this;
	}

	public function setLocalPath( string $localPath, bool $GeneratePublicURL = false, bool $CreateIfNotExists = true ){
		if( substr( $localPath , -1 ) != '/' ){
			$localPath .= '/';	// Ensure that it has a trailing slash
		}

		if( !file_exists( $localPath ) && $CreateIfNotExists ){
			$this->FileController->makeDirectory( $localPath );
		}

		$this->CacheController->setLocalPath( $localPath );

		// Attempt to determine the public URL for the cache directory
		if( $GeneratePublicURL ){
			$this->generatePublicURL();
		}


		if( $this->cacheInstantiated )
			$this->reloadCache();

		return $this;
	}

	public function setLocalPublicURL( string $localPublicURL ){
		$this->localPublicURL = $localPublicURL;

		return $this;
	}

	public function setWriteEveryChange( bool $writeEveryChange ){
		$this->writeEveryChange = $writeEveryChange;

		return $this;
	}

	public function setCleanupOnDestruct( bool $cleanupOnDestruct ){
		$this->cleanupOnDestruct = $cleanupOnDestruct;

		return $this;
	}

	public function setUserAgent( string $userAgent ){
		return $this->FileController->setUserAgent( $userAgent );
	}
	/*
	 * End: Setters
	 */





	/**
	 * Ensures that the cache exists for the current configuration. This is called every time media is attempted to be cached 
	 *
	 * @return Boolean
	 */
	protected function instantiateCache(){
		if( $this->cacheInstantiated == true )
			return true;

		$this->FileController->makeDirectory( $this->getLocalPath() );


		$CacheStatus = $this->CacheController->instantiateCache();

		if( $CacheStatus === false ){
			return false;
		}

		$this->cacheInstantiated = $CacheStatus;

		return $CacheStatus;
	}


	/**
	 * Attempt to generate the public URL for the local media caching directory.
	 *
	 * @return bool
	 */
	protected function generatePublicURL(){
		$localPath = $this->CacheController->getLocalPath();
		if( empty( $localPath ) )
			return false;

		if( get_class($this->FileController) !== "FreshVine\ExpiringMediaCache\Controllers\FileLocalController" )
			return false;

		// Estimate the public URL to the media-cache directory
		$urlProtocol = stripos($_SERVER['SERVER_PROTOCOL'],'https') === 0 ? 'https://' : 'http://';
		if( stripos( $localPath, $_SERVER['DOCUMENT_ROOT'] ) !== 0 )
			return false;	//


		// Remove the document root fromt he current files directory. This will give us the default public URL path to the library directory
		$urlPath = trim( substr( $localPath, strlen( $_SERVER['DOCUMENT_ROOT'] ) ), '/' );
		if( strlen( $urlPath ) > 0 ){
			$urlPath .= '/';	// If there is a directory we need to include a trailing slash.
		}

		$PublicURLGuess = $urlProtocol . $_SERVER['HTTP_HOST'] . '/' . $urlPath;
		$this->setLocalPublicURL( $PublicURLGuess );

		return true;
	}



	/*
	 * Passthrough methods
	 */
	public function exists( FileModel $File ){
		return $this->FileController->exists( $File );
	}
	public function fileExists( string $filename ){
		return $this->FileController->fileExists( $filename );
	}
	public function fileRead( string $filename ){
		return $this->FileController->fileRead( $filename );
	}
	public function fileSize( string $filename ){
		return $this->FileController->fileSize( $filename );
	}
	public function fileWrite( string $filename, string $content ){
		return $this->FileController->fileWrite( $filename, $content );
	}
	public function writeCache(){
		$this->CacheController->writeCache();
	}
	/*
	 * END: Passthrough methods
	 */



	/*
	 * Static methods
	 */
	static function instance( $name = 'default'){
		if ( isset(self::$instances[$name] ) ){
			return self::$instances[$name];
		}

		$instance = new static();

		self::$instances[$name] = $instance;

		return $instance;
	}
	/*
	 * END: Static methods
	 */
}
?>