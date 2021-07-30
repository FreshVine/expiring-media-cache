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
use FreshVine\ExpiringMediaCache\Models\File as FileModel;

abstract class File{

	/*
	 *	Establish Members
	 */
	protected	$ExpiringMediaCache;			// A passed instance of the instantiator of this class
	protected	$UserAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36';

	public function __construct( ExpiringMediaCache $ExpiringMediaCache = NULL ){
		if( !is_null( $ExpiringMediaCache ) )
			$this->ExpiringMediaCache = $ExpiringMediaCache;

		return $this;
	}



	/*
	 *  Getters
	 */
	public function getUserAgent(){
		return $this->UserAgent;
	}

	/*
	 * Setters
	 */
	public function setUserAgent( string $UserAgent ){
		$this->UserAgent = $UserAgent;

		return $this;
	}


	/**
	 * Will check for locally cached media which have expired and remove them from the cache index, and the file system.
	 *
	 * @param  URL		$RemoteURL		The text object provided by the source
	 * @param  STRING	$Extension		What is the extension for the media we are fetching
	 * @return FileModel
	 */
	public function fetchRemoteMedia( string $RemoteURL, FileModel &$File ){
		//
		// Create a stream
		$StreamContextOptions = array(
			'http'=>array(
				'method'=>"GET",
				'header'=>
					"accept-encoding: gzip, deflate, br\r\n" .
					"accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9\r\n",
				'user_agent' => $this->getUserAgent(),
				'max_redirects' => 100,
				'follow_location' => true
			)
		);

		$StreamContext = stream_context_create($StreamContextOptions);
		// Create the stream context
		//


		// Open the file and read its content using the HTTP headers set above
		$MediaContents = file_get_contents($RemoteURL, false, $StreamContext);

		// Store the media content into the file model
		$File->setContent( $MediaContents );

		return $File;
	}


	/**
	 * Takes a supplied filename and cleans it to ensure there are not illegal characters
	 *
	 * @return string
	 */
	public function cleanStringForFilename( string $string = NULL ){
		if( is_null( $string ) ){
			return NULL;
		}

		// List of unallowed strings for the filename
		$badCharacters = array( '?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', '’', '«', '»', '”', '“', chr( 0 ) );

		// Just remove any instance of those bad characters
		$string = str_replace($badCharacters, '', $string);	// Remove bad chars
		$string = str_replace( array( '%20', '+' ), '-', $string );	// Convert spaces

		return $string;
	}


	/**
	 * Checked the filename to ensure that a unique filename is used for this file
	 *
	 * @param  FileModel		$File		This is the File model that holds the information about the file
	 * @return FileModel
	 */
	public function uniqueFilename( FileModel &$File ){
		if( $this->exists( $File ) == true ){
			$LoopCounter = 1;
			while( $this->exists( $File ) == true ){
				$NameParts = pathinfo( $File->getFilename() );

				if( strripos( $NameParts['filename'], '-' ) === strripos( $NameParts['filename'], '-v' )
					&& is_numeric( substr( $NameParts['filename'], (strripos( $NameParts['filename'], '-v' ) + 2) ) ) ){
					// This string has a suffix that starts with v. Lets see if the remainder is an integer, and if it is we know this was previously versioned this way and we can remove this bit from the filename.

					$NameParts['filename'] = substr( $NameParts['filename'], 0, strripos( $NameParts['filename'], '-' ) );
				}

				$File->setFilename( $NameParts['filename'] . '-v' . str_pad( $LoopCounter, 2, '0', STR_PAD_LEFT ) . '.' . $NameParts['extension'] );
				++$LoopCounter;
			}
		}

		return $File;	// This filename is good
	}



	/**
	 * Returns a list of all the files in the given media cache directory
	 *
	 * @return array
	 */
	abstract public function listFiles();


	/**
	 * Determins if the supplied file exists or not
	 *
	 * @param  FileModel		$File		This is the File model that holds the information about the file
	 * @return boolean
	 */
	abstract public function exists( FileModel $File );


	/**
	 * Removes the given file from the file system
	 *
	 * @param  FileModel		$File		This is the File model that holds the information about the file
	 * @return FileModel
	 */
	abstract public function delete( FileModel &$File );


	/**
	 * Returns the size of the file in bytes from the filesystem
	 *
	 * @param  FileModel		$File		This is the File model that holds the information about the file
	 * @return FileModel
	 */
	abstract public function size( FileModel &$File );


	/**
	 * Writes a file to the desired file system
	 *
	 * @param  FileModel		$File		This is the File model that holds the information about the file
	 * @return FileModel
	 */
	abstract public function write( FileModel &$File );


	/**
	 * Determins if the supplied filename or path exists
	 *
	 * @param  string			$FullFilePath		This is the absolute path to check for
	 * @return boolean
	 */
	abstract public function fileExists( string $FullFilePath );


	/**
	 * Deletes the supplied filename or path exists
	 *
	 * @param  string			$FullFilePath		This is the absolute path to check for
	 * @return boolean
	 */
	abstract public function fileDelete( string $FullFilePath );


	/**
	 * Return the content from the supplied filename
	 *
	 * @param  string			$FullFilePath		This is the absolute path to check for
	 * @return string
	 */
	abstract public function fileRead( string $FullFilePath );


	/**
	 * Returns the size of the file in bytes from the filesystem
	 *
	 * @param  FileModel		$File		This is the File model that holds the information about the file
	 * @return int
	 */
	abstract public function fileSize( string $FullFilePath );


	/**
	 * Write the given content to the given filename
	 *
	 * @param  string			$FullFilePath		This is the absolute path to check for
	 * @return boolean
	 */
	abstract public function fileWrite( string $FullFilePath, string $Content );
}
?>