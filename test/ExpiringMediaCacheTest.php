<?php

use PHPUnit\Framework\TestCase;
use FreshVine\ExpiringMediaCache\ExpiringMediaCache as ExpiringMediaCache;

class ExpiringMediaCacheTest extends TestCase
{
    final function __construct($name = null, array $data = array(), $dataName = '')
    {
        $this->dirs = $this->initDirs();
        $this->ExpiringMediaCache = $this->initExpiringMediaCache();

        parent::__construct($name, $data, $dataName);
    }

    private $dirs;
    protected $ExpiringMediaCache;

    /**
     * @return array
     */
    protected function initDirs()
    {
        $dirs []= dirname(__FILE__).'/data/';

        return $dirs;
    }

    /**
     * @return ExpiringMediaCache
     */
    protected function initExpiringMediaCache()
    {
        $ExpiringMediaCache = new TestExpiringMediaCache();
        return $ExpiringMediaCache;
    }

    /**
     * @dataProvider data
     * @param $test
     * @param $dir
     */
    function test_($test, $dir)
    {
    }

    function testRawHtml()
    {
    }

    function testTrustDelegatedRawHtml()
    {
    }

    function data()
    {
        $data = array();
        }

        return $data;
    }

    public function testLateStaticBinding()
    {
        $ExpiringMediaCache = ExpiringMediaCache::instance();
    }
}