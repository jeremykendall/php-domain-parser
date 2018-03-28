<?php

declare(strict_types=1);

namespace Pdp\Tests;

use org\bovigo\vfs\vfsStream;
use Pdp\Cache;
use Pdp\Converter;
use Pdp\CurlHttpClient;
use Pdp\Exception;
use Pdp\Manager;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * @coversDefaultClass Pdp\Manager
 */
class ManagerTest extends TestCase
{
    protected $cachePool;
    protected $cacheDir;
    protected $root;
    protected $sourceUrl = 'https://publicsuffix.org/list/public_suffix_list.dat';

    public function setUp()
    {
        $this->root = vfsStream::setup('pdp');
        vfsStream::create(['cache' => []], $this->root);
        $this->cacheDir = vfsStream::url('pdp/cache');
        $this->cachePool = new Cache($this->cacheDir);
    }

    public function tearDown()
    {
        $this->cachePool = null;
        $this->cacheDir = null;
        $this->root = null;
    }

    /**
     * @covers ::__construct
     * @covers ::getRules
     * @covers ::getCacheKey
     * @covers ::refreshRules
     * @covers \Pdp\Converter
     */
    public function testRefreshRules()
    {
        $manager = new Manager($this->cachePool, new CurlHttpClient());
        $previous = $manager->getRules();
        $this->assertTrue($manager->refreshRules($this->sourceUrl));
        $this->assertEquals($previous, $manager->getRules());
    }

    /**
     * @covers ::__construct
     * @covers ::getRules
     * @covers ::getCacheKey
     * @covers ::refreshRules
     * @covers \Pdp\Converter
     */
    public function testRebuildRulesFromRemoveSource()
    {
        $manager = new Manager($this->cachePool, new CurlHttpClient());
        $previous = $manager->getRules($this->sourceUrl);
        $this->cachePool->clear(); //delete all local cache
        $list = $manager->getRules($this->sourceUrl);
        $this->assertEquals($previous, $manager->getRules($this->sourceUrl));
    }

    /**
     * @covers ::__construct
     * @covers ::getRules
     * @covers ::getCacheKey
     * @covers ::refreshRules
     * @covers \Pdp\Converter
     */
    public function testGetRulesThrowsExceptionIfNotCacheCanBeRetrieveOrRefresh()
    {
        $cachePool = new class() implements CacheInterface {
            public function get($key, $default = null)
            {
                return null;
            }

            public function set($key, $value, $ttl = null)
            {
                return false;
            }

            public function delete($key)
            {
                return true;
            }

            public function clear()
            {
                return true;
            }

            public function getMultiple($keys, $default = null)
            {
                return [];
            }

            public function setMultiple($values, $ttl = null)
            {
                return true;
            }
            public function deleteMultiple($keys)
            {
                return true;
            }

            public function has($key)
            {
                return true;
            }
        };

        $this->expectException(Exception::class);
        $manager = new Manager($cachePool, new CurlHttpClient());
        $manager->getRules('https://google.com');
    }


    /**
     * @covers ::__construct
     * @covers ::getRules
     */
    public function testGetRulesThrowsExceptionIfTheCacheIsCorrupted()
    {
        $cachePool = new class() implements CacheInterface {
            public function get($key, $default = null)
            {
                return '{"foo":"bar",}'; //malformed json
            }

            public function set($key, $value, $ttl = null)
            {
                return false;
            }

            public function delete($key)
            {
                return true;
            }

            public function clear()
            {
                return true;
            }

            public function getMultiple($keys, $default = null)
            {
                return [];
            }

            public function setMultiple($values, $ttl = null)
            {
                return true;
            }
            public function deleteMultiple($keys)
            {
                return true;
            }

            public function has($key)
            {
                return true;
            }
        };

        $this->expectException(Exception::class);
        $manager = new Manager($cachePool, new CurlHttpClient());
        $manager->getRules();
    }

    /**
     * @covers \Pdp\Converter::convert
     * @covers \Pdp\Converter::getSection
     * @covers \Pdp\Converter::addRule
     * @covers \Pdp\Converter::idnToAscii
     */
    public function testConvertThrowsExceptionWithInvalidContent()
    {
        $this->expectException(Exception::class);
        $content = file_get_contents(__DIR__.'/data/invalid_suffix_list_content.dat');
        (new Converter())->convert($content);
    }
}
