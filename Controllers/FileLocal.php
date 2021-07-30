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
use FreshVine\ExpiringMediaCache\Controllers\File as FileController;
use FreshVine\ExpiringMediaCache\Models\File as FileModel;

class FileLocal extends FileController{
	/**
	 * Creates the directory for the given absolute path
	 *
	 * @return boolean
	 */
	public function makeDirectory( string $DirectoryPath ){
		if( is_dir( $DirectoryPath ) )
			return false;

		if( file_exists( $DirectoryPath ) )
			return true;

		$Response = mkdir( $DirectoryPath, 0777, true );

		return $Response;
	}


	/**
	 * Returns a list of all the files in the given media cache directory
	 *
	 * @return array
	 */
	public function listFiles(){
		if( !file_exists( $this->ExpiringMediaCache->getLocalPath() ) )
			return array();
		
		$Response = array_diff(scandir( $this->ExpiringMediaCache->getLocalPath() ), array('..', '.'));

		return $Response;
	}


	/**
	 * Determins if the supplied file exists or not
	 *
	 * @param  FileModel		$File		This is the File model that holds the information about the file
	 * @return boolean
	 */
	public function exists( FileModel &$File ){
		$FullFilePath = $this->ExpiringMediaCache->getLocalPath() . $File->getFilename();
		if( is_file( $FullFilePath ) !== true ){
			$File->setStatus('unwritten');
			return false;
		}

		return true;
	}


	/**
	 * Removes the given file from the file system
	 *
	 * @param  FileModel		$File		This is the File model that holds the information about the file
	 * @return FileModel
	 */
	public function delete( FileModel &$File ){
		$FullFilePath = $this->ExpiringMediaCache->getLocalPath() . $File->getFilename();

		if( $this->exists( $File ) == false ){
			throw new \Exception('ExpiringMediaCache: Unable to remove file that does not exist - ' . $File->getFilename() );
		}

		
		$Success = unlink( $FullFilePath );
		if( $Success === false ){
			throw new \Exception('ExpiringMediaCache: Unable to remove the file - ' . $File->getFilename() );
		}

		$File->setFilesize( 0 );
		$File->setStatus('removed');

		return $File;
	}


	/**
	 * Returns the size of the file in bytes from the filesystem
	 *
	 * @param  FileModel		$File		This is the File model that holds the information about the file
	 * @return FileModel
	 */
	public function size( FileModel &$File ){
		$Filesize =  filesize( $this->ExpiringMediaCache->getLocalPath() . $File->getFilename() );
		$File->setFilesize( $Filesize );

		return $File;
	}


	/**
	 * Writes a file to the desired file system
	 *
	 * @param  FileModel		$File		This is the File model that holds the information about the file
	 * @return FileModel
	 */
	public function write( FileModel &$File ){
		$FullFilePath = $this->ExpiringMediaCache->getLocalPath() . $File->getFilename();


		// Check that the file has content
		if( empty( $File->getContent() ) ){
			throw new \Exception('ExpiringMediaCache: Unable to save file with no content - ' . $File->getFilename() );
		}


		// Check that the filename has not already been used
		if( is_file( $FullFilePath )){
			throw new \Exception('ExpiringMediaCache: Filename is arleady in use - ' . $File->getFilename() );
		}


		// Attempt to write the file
		$Filesize = file_put_contents( $FullFilePath, $File->getContent() );
		if( $Filesize === false ){
			throw new \Exception('ExpiringMediaCache: Unable to save file to location - ' . $FullFilePath );
		}

		$File->setFilesize( $Filesize );	// 
		$File->setStatus('written');		// Change the status to written
		$File->setContent( '' );			// Clear the content from the object now that it is written

		// Return the file model
		return $File;
	}


	/**
	 * Determins if the supplied filename or path exists
	 *
	 * @param  string			$FullFilePath		This is the absolute path to check for
	 * @return boolean
	 */
	public function fileExists( string $FullFilePath ){
		if( is_file( $FullFilePath ) !== true )
			return false;

		return true;
	}


	/**
	 * Deletes the supplied filename or path exists
	 *
	 * @param  string			$FullFilePath		This is the absolute path to check for
	 * @return boolean
	 */
	public function fileDelete( string $FullFilePath ){
		if( $this->fileExists( $FullFilePath ) == false ){
			return false;
		}

		
		$Success = unlink( $FullFilePath );
		if( $Success === false ){
			false;
		}

		return true;
	}


	/**
	 * Return the content from the supplied filename
	 *
	 * @param  string			$FullFilePath		This is the absolute path to check for
	 * @return string
	 */
	public function fileRead( string $FullFilePath ){
		if( !$this->fileExists( $FullFilePath ) )
			return NULL;

		return file_get_contents( $FullFilePath );
	}


	/**
	 * Determins if the supplied filename or path exists
	 *
	 * @param  string			$FullFilePath		This is the absolute path to check for
	 * @return int
	 */
	public function fileSize( string $FullFilePath ){
		if( $this->fileExists( $FullFilePath ) !== true )
			return 0;

		return filesize( $FullFilePath );
	}


	/**
	 * Write the given content to the given filename
	 *
	 * @param  string			$FullFilePath		This is the absolute path to check for
	 * @return boolean
	 */
	public function fileWrite( string $FullFilePath, string $Content ){
		// Attempt to write the file
		$Filesize = file_put_contents( $FullFilePath, $Content );
		if( $Filesize === false ){
			throw new \Exception('ExpiringMediaCache: Unable to save file to location - ' . $FullFilePath );
		}

		return true;
	}
}

?>