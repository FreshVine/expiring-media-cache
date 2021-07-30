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
		$Media = array();
		try{
			$Media[] = $this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . 'Northern_Hemisphere_Snow_Cover_Graph.png');	// PNG
			$Media[] = $this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . 'chapel-cluny-museum.jpg');	// JPG
			$Media[] = $this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . 'Dipole_xmting_antenna_animation.gif');	// GIF
			$Media[] = $this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . 'beaver.svg');	// SVG
		}catch( Exception $e ){
			$this->assertFalse(true, 'Failed to cache Media');
		}


		$this->ExpiringMediaCache->writeCache();


		// Ensure that the files were cached
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'Northern_Hemisphere_Snow_Cover_Graph.png', 'Media Image did not cache - PNG');
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'chapel-cluny-museum.jpg', 'Media Image did not cache - JPG');
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'Dipole_xmting_antenna_animation.gif', 'Media Image did not cache - GIF');
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'beaver.svg', 'Media Image did not cache - SVG');


		// Ensure that the files were the same as the remote ones
		$this->assertImagesSame( $this->ExpiringMediaCache->getLocalPath() . 'chapel-cluny-museum.jpg', __DIR__ . '/images/chapel-cluny-museum.jpg' );
	}


	/**
	 * Test the suffixing when different files with the same filename are attempted to be cached
	 */
	function testSameFilenames(){
		$Media = array();
		$Media['cluny'] = $this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . '01/copy-test.jpg?match=cluny' );
		$Media['lucy'] = $this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . '02/copy-test.jpg?match=lucy' );
		$Media['cannes'] = $this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . '03/copy-test.jpg?match=cannes' );

		$this->ExpiringMediaCache->writeCache();

		// Ensure that the media were cached with the right file names now exist
		$this->assertEquals( $Media['cluny']->getCacheFilename(), 'copy-test.jpg', "Cache Filename does not match expectation");
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'copy-test.jpg');

		$this->assertEquals( $Media['lucy']->getCacheFilename(), 'copy-test-v01.jpg', "Cache Filename does not match expectation");
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'copy-test-v01.jpg');

		$this->assertEquals( $Media['cannes']->getCacheFilename(), 'copy-test-v02.jpg', "Cache Filename does not match expectation");
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'copy-test-v02.jpg');


		// Ensure that the files were the same as the remote ones - these copies are smaller to save space, so we are checking for similarity
		$this->assertImageSimilarity( $this->ExpiringMediaCache->getLocalPath() . $Media['cluny']->getCacheFilename(), __DIR__ . '/images/chapel-cluny-museum.jpg', 0.1 );
		$this->assertImageSimilarity( $this->ExpiringMediaCache->getLocalPath() . $Media['lucy']->getCacheFilename(), __DIR__ . '/images/lucy-as-kitten-2.jpg', 0.1 );
		$this->assertImageSimilarity( $this->ExpiringMediaCache->getLocalPath() . $Media['cannes']->getCacheFilename(), __DIR__ . '/images/ville-de-cannes.jpg', 0.1 );
	}


	/**
	 * The purpose of this test is to see if we will refetch missing media if the user requests that media.
	 */
	function testRefetchMedia(){
		// First Fetch
		$Media = $this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . 'snowman-at-albright-visitor-center.jpg' );
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'snowman-at-albright-visitor-center.jpg');


		unlink( $this->ExpiringMediaCache->getLocalPath() . 'snowman-at-albright-visitor-center.jpg' );	// Remove the Media Directly
		$this->assertFileDoesNotExist( $this->ExpiringMediaCache->getLocalPath() . 'snowman-at-albright-visitor-center.jpg');


		$MediaAgain = $this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . 'snowman-at-albright-visitor-center.jpg' );
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'snowman-at-albright-visitor-center.jpg');
	}


	/**
	 * The purpose of this test is to ensure that prefixes are added to local filenames
	 */
	function testPrefix(){
		unlink( $this->ExpiringMediaCache->getLocalPath() . 'chapel-cluny-museum.jpg' );	// Remove the Media Directly

		$this->ExpiringMediaCache->cleanUp();
		$this->ExpiringMediaCache->writeCache();
		$this->ExpiringMediaCache->reloadCache();
		

		$this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . 'chapel-cluny-museum.jpg', 'prefix' );
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'prefix-chapel-cluny-museum.jpg');
	}


	/**
	 * The purpose of this test is to ensure that suffixes are added to local filenames
	 */
	function testSuffix(){
		unlink( $this->ExpiringMediaCache->getLocalPath() . 'prefix-chapel-cluny-museum.jpg' );	// Remove the Media Directly

		$this->ExpiringMediaCache->cleanUp();
		$this->ExpiringMediaCache->writeCache();
		$this->ExpiringMediaCache->reloadCache();


		$this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . 'chapel-cluny-museum.jpg', null, 'suffix');
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'chapel-cluny-museum-suffix.jpg');
	}


	/**
	 * The purpose of this test is to ensure that prefixes and suffixes are added to local filenames
	 */
	function testPrefixSuffix(){
		unlink( $this->ExpiringMediaCache->getLocalPath() . 'chapel-cluny-museum-suffix.jpg' );	// Remove the Media Directly

		$this->ExpiringMediaCache->cleanUp();
		$this->ExpiringMediaCache->writeCache();
		$this->ExpiringMediaCache->reloadCache();

		$this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . 'chapel-cluny-museum.jpg', 'prefix', 'suffix');
		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'prefix-chapel-cluny-museum-suffix.jpg');
	}




	/**
	 * Check what happens to media when it has expired. Expired media should be removed from the cache file, and related media should be removed
	 */
	function testExpiredMedia(){
		/**
		 *  Media that is expired should be removed if it is not requested during this instance.
		 */
		// Load a sample image
		$Sample = $this->ExpiringMediaCache->cacheThis( ExpiringMediaCacheTest::GithubRawURL . 'great-gallery-of-evolution.jpg' );

		$this->assertFileExists( $this->ExpiringMediaCache->getLocalPath() . 'great-gallery-of-evolution.jpg');
		$RemoteURL = $Sample->getRemoteURL();
		$this->assertFalse( $Sample->isExpired() );		// Ensure that is was false when it was cached

		// Write the cache to the JSON file
		$this->ExpiringMediaCache->writeCache();

	
		// Access the contents of the JSON file, Set the timestamp back 1000 years (it's easy)
		$contents = json_decode( file_get_contents( $this->ExpiringMediaCache->getLocalPath() . '_media-cache.json' ), true );
		$contents['media'][$RemoteURL]['Timestamp'] = '1' . substr( $contents['media'][$RemoteURL]['Timestamp'], 1 );

		file_put_contents( $this->ExpiringMediaCache->getLocalPath() . '_media-cache.json', json_encode( $contents, JSON_UNESCAPED_UNICODE ) );


		// Loading the cache again  from the JSON file will mark the media as expired
		$this->ExpiringMediaCache->reloadCache();
		$SampleExpired = $this->ExpiringMediaCache->find( $RemoteURL );	// Find the same media from before
		$this->assertTrue( $SampleExpired->isExpired() );	// Ensure that it is now expired


		$this->ExpiringMediaCache->cleanUp();	// This should remove any expired media images.
		$this->assertFileDoesNotExist( $this->ExpiringMediaCache->getLocalPath() . 'great-gallery-of-evolution.jpg', 'Expired file remained after clean up' );



		// Write the cache to the JSON file
		$this->ExpiringMediaCache->writeCache();

		// Check that the expired media was not included
		$ExpiredContents = json_decode( file_get_contents( $this->ExpiringMediaCache->getLocalPath() . '_media-cache.json' ), true );
		$this->assertFalse( array_key_exists( $RemoteURL, $ExpiredContents['media'] ), 'Cache included an entry for expired media');

	}


	/**
	 * There should be no excess files or directories in the cache directory. We should ensure that the files which we place into this directory are removed when the destructor runs.
	 */
	function testExcessFiles(){
		$OG_Filename = __DIR__ . '/images/ville-de-cannes.jpg';
		$ExcessFilename = __DIR__ . '/media-cache/ville-de-cannes-extra-file.jpg';


		if( !copy( $OG_Filename, $ExcessFilename ) ){
			throw new Exception('ExpiringMediaCacheTest: Failed when coping a file into the media-cache directory.');
		}


		$this->ExpiringMediaCache->cleanUp();
		$this->ExpiringMediaCache->writeCache();


		$this->assertFileDoesNotExist( $ExcessFilename, 'Excess file remained after running the clean up' );
		
	}



	public function testLateStaticBinding(){
		$ExpiringMediaCache = ExpiringMediaCache::instance();
		$this->assertInstanceOf('FreshVine\ExpiringMediaCache\ExpiringMediaCache', $ExpiringMediaCache);

		// After instance is already called on ExpiringMediaCache
		// subsequent calls with the same arguments return the same instance
		$sameExpiringMediaCache = TestExpiringMediaCache::instance();
		$this->assertInstanceOf('FreshVine\ExpiringMediaCache\ExpiringMediaCache', $sameExpiringMediaCache);
		$this->assertSame($ExpiringMediaCache, $sameExpiringMediaCache);

		$testExpiringMediaCache = TestExpiringMediaCache::instance('test late static binding');
		$this->assertInstanceOf('TestExpiringMediaCache', $testExpiringMediaCache);

		$sameInstanceAgain = TestExpiringMediaCache::instance('test late static binding');
		$this->assertSame($testExpiringMediaCache, $sameInstanceAgain);
	}



	/**
	 * This is here to ensure that we clean everything up after each run. Since this is a caching library having files lingering could screw up our results.
	 */
	public static function setUpBeforeClass(): void {
		$TemporaryDirectories = array(
			__DIR__ . '/media-cache/',
			__DIR__ . '/another-cache/'
		);

		foreach( $TemporaryDirectories as $dir ){
			if( !file_exists( $dir ) )
				continue;

			$files = array_diff(scandir($dir), array('.','..'));
			foreach ($files as $file) {
				unlink("$dir/$file");
			}

			rmdir( $dir );
		}
	}


	/**
	 * This is here to ensure that we clean everything up after each run. Since this is a caching library having files lingering could screw up our results.
	 */
	public static function tearDownAfterClass(): void {
		// We need to clean up the directories that we made
		$TemporaryDirectories = array(
			__DIR__ . '/media-cache/',
			__DIR__ . '/another-cache/'
		);

		foreach( $TemporaryDirectories as $dir ){
			if( !file_exists( $dir ) )
				continue;

			$files = array_diff(scandir($dir), array('.','..'));
			foreach ($files as $file) {
				unlink("$dir/$file");
			}
		}

		return;
	}
}