<?php


use PHPUnit\Framework\TestCase;
use Lupka\PHPUnitCompareImages\CompareImagesTrait;
use FreshVine\ExpiringMediaCache\ExpiringMediaCache as ExpiringMediaCache;


class ExpiringMediaCacheTest extends TestCase{
	use CompareImagesTrait;

	final function __construct($name = null, array $data = array(), $dataName = ''){
		$this->dirs = $this->initDirs();
		$this->ExpiringMediaCache = $this->initExpiringMediaCache();

		parent::__construct($name, $data, $dataName);
	}

	private $dirs;
	protected $ExpiringMediaCache;
	const GithubRawURL = 'https://github.com/FreshVine/expiring-media-cache/raw/main/test/images/';

	/**
	 * @return array
	 */
	protected function initDirs(){
		$dirs []=  __DIR__ . '/images/';

		return $dirs;
	}

	/**
	 * @return ExpiringMediaCache
	 */
	protected function initExpiringMediaCache(){
		$ExpiringMediaCache = new TestExpiringMediaCache( rtrim( __DIR__, '/' ) . '/media-cache/' );

		return $ExpiringMediaCache;
	}


	/**
	 * Ensure that the images are cached, and that the images match those from the remote server
	 */
	function testBasicImages(){
		try{
			$Media = array();
			$Media[] = $this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . 'Northern_Hemisphere_Snow_Cover_Graph.png');	// PNG
			$Media[] = $this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . 'chapel-cluny-museum.jpg');	// JPG
			$Media[] = $this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . 'Dipole_xmting_antenna_animation.gif');	// GIF
			$Media[] = $this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . 'beaver.svg');	// SVG
		}catch( Exception $e ){
			throw new $e;
		}


		// Ensure that the files were cached
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'Northern_Hemisphere_Snow_Cover_Graph.png', 'Media Image did not cache - PNG');
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'chapel-cluny-museum.jpg', 'Media Image did not cache - JPG');
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'Dipole_xmting_antenna_animation.gif', 'Media Image did not cache - GIF');
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'beaver.svg', 'Media Image did not cache - SVG');


		// Ensure that the files were the same as the remote ones
		$this->assertImagesSame( $this->ExpiringMediaCache->getLocalPath() . 'chapel-cluny-museum.jpg', __DIR__ . '/images/chapel-cluny-museum.jpg' );
	}

	}
}