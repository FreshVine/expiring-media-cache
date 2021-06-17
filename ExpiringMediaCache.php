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

class ExpiringMediaCache{

	// Establish the version of the library
	const version = '0.1';


	/*
	 *	Establish the variables
	 */
	protected	$cacheDuration = 7 * 24 * 60;		// Int: The number of minutes before the cache should expire and remove media [default: 7 days]
	protected	$cachePoint = 'First';		// Enum/Set: From what point in time do we use the cache. From the first request, or the most recent request. [First, Request] Default is 'First'

	private static $instances = array();


	/*
	 * Configuration Setters
	 */
	public function setCacheDuration(int $cacheDuration){
		$this->cacheDuration = $cacheDuration;

		return $this;
	}


	function setCachePoint($cachePoint){
		$this->cachePoint = $cachePoint;

		return $this;
	}

	/*
	 * End: Configuration Setters
	 */


	/*
	 * Static methods
	 */
	static function instance($name = 'default'){
		if (isset(self::$instances[$name])){
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