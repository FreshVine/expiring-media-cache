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

class File{
	/* -- Media Members -- */
	protected $Filename;	// Required
	protected $Content;			// Optional - this is the content of the file
	protected $Filesize;
	protected $Status = 'written';		// Required - SET[unwritten, written, removed]


	public function __construct( String $Filename, String $Content = NULL ){
		$this->setFilename( $Filename );

		if( !is_null( $Content ) ){
			$this->setContent( $Content );
			$this->setStatus('unwritten');
		}

		return $this;
	}


	/*
	 *  Getters
	 */
	public function getFilename(){
		return $this->Filename;
	}
	public function getContent(){
		return $this->Content;
	}
	public function getFilesize(){
		return $this->Filesize;
	}
	public function getStatus(){
		return $this->Status;
	}


	/*
	 * Setters
	 */
	public function setFilename( string $Filename ){
		$this->Filename = $Filename;

		return $this;
	}
	public function setContent( string $Content ){
		$this->Content = $Content;

		return $this;
	}
	public function setFilesize( int $Filesize ){
		$this->Filesize = $Filesize;

		return $this;
	}
	public function setStatus( string $Status ){
		if( !in_array( strtolower( $Status ), array('unwritten', 'written', 'removed') ) )
			throw new \Exception('ExpiringMediaCache: Attempted to use an invalid File status of \'\'. Allowed values: unwritten, written, removed');

		$this->Status = strtolower( $Status );

		return $this;
	}
}
?> 