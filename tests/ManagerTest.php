<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp\Tests;

use DateInterval;
use DateTime;
use org\bovigo\vfs\vfsStream;
use Pdp\Cache;
use Pdp\Converter;
use Pdp\CurlHttpClient;
use Pdp\Exception;
use Pdp\Exception\CouldNotLoadRules;
use Pdp\Exception\CouldNotLoadTLDs;
use Pdp\Exception\InvalidDomain;
use Pdp\HttpClient;
use Pdp\Manager;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use TypeError;

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
        self::assertTrue($manager->refreshRules($this->sourceUrl));
        self::assertEquals($previous, $manager->getRules());
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
        self::assertEquals($previous, $manager->getRules($this->sourceUrl));
    }

    /**
     * @covers ::__construct
     * @covers ::getTLDs
     * @covers ::getCacheKey
     * @covers ::refreshTLDs
     * @covers \Pdp\Converter
     */
    public function testRefreshTLDs()
    {
        $client = new class() implements HttpClient {
            public function getContent(string $url): string
            {
                if ($url === Manager::PSL_URL) {
                    return file_get_contents(__DIR__.'/data/public_suffix_list.dat');
                }

                if ($url === Manager::RZD_URL) {
                    return file_get_contents(__DIR__.'/data/tlds-alpha-by-domain.txt');
                }

                return '';
            }
        };

        $manager = new Manager($this->cachePool, $client);
        $previous = $manager->getTLDs();
        self::assertTrue($manager->refreshTLDs());
        self::assertEquals($previous, $manager->getTLDs());
    }

    /**
     * @covers ::__construct
     * @covers ::getTLDs
     * @covers ::getCacheKey
     * @covers ::refreshTLDs
     * @covers \Pdp\Converter
     */
    public function testRebuildTLDsFromRemoveSource()
    {
        $client = new class() implements HttpClient {
            public function getContent(string $url): string
            {
                if ($url === Manager::PSL_URL) {
                    return file_get_contents(__DIR__.'/data/public_suffix_list.dat');
                }

                if ($url === Manager::RZD_URL) {
                    return file_get_contents(__DIR__.'/data/tlds-alpha-by-domain.txt');
                }

                return '';
            }
        };

        $manager = new Manager($this->cachePool, $client);
        $previous = $manager->getTLDs();
        $this->cachePool->clear(); //delete all local cache
        self::assertEquals($previous, $manager->getTLDs());
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

        self::expectException(CouldNotLoadRules::class);
        $manager = new Manager($cachePool, new CurlHttpClient());
        $manager->getRules('https://google.com');
    }

    /**
     * @covers ::__construct
     * @covers ::getTLDs
     * @covers ::getCacheKey
     * @covers ::refreshTLDs
     * @covers \Pdp\Converter
     */
    public function testGetTLDsThrowsExceptionIfNotCacheCanBeRetrieveOrRefresh()
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

        self::expectException(CouldNotLoadTLDs::class);
        $manager = new Manager($cachePool, new CurlHttpClient());
        $manager->getTLDs();
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

        self::expectException(CouldNotLoadRules::class);
        $manager = new Manager($cachePool, new CurlHttpClient());
        $manager->getRules();
    }

    /**
     * @covers ::__construct
     * @covers ::getTLDs
     */
    public function testGetTLDsThrowsExceptionIfTheCacheIsCorrupted()
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

        self::expectException(CouldNotLoadTLDs::class);
        $manager = new Manager($cachePool, new CurlHttpClient());
        $manager->getTLDs();
    }

    /**
     * @covers \Pdp\Converter::convert
     * @covers \Pdp\Converter::getSection
     * @covers \Pdp\Converter::addRule
     * @covers \Pdp\Converter::idnToAscii
     */
    public function testConvertThrowsExceptionWithInvalidContent()
    {
        self::expectException(InvalidDomain::class);
        $content = file_get_contents(__DIR__.'/data/invalid_suffix_list_content.dat');
        (new Converter())->convert($content);
    }

    /**
     * @covers \Pdp\Converter::convertRootZoneDatabase
     * @covers \Pdp\Converter::getHeaderInfo
     */
    public function testConvertRootZoneDatabaseThrowsExceptionWithInvalidContent()
    {
        self::expectException(Exception::class);
        $content = file_get_contents(__DIR__.'/data/invalid_suffix_list_content.dat');
        (new Converter())->convertRootZoneDatabase($content);
    }

    /**
     * @dataProvider validTtlProvider
     */
    public function testSettingTtl($ttl)
    {
        self::assertInstanceOf(Manager::class, new Manager(new Cache(), new CurlHttpClient(), $ttl));
    }

    public function validTtlProvider()
    {
        return [
            'DateInterval' => [new DateInterval('PT1H')],
            'null' => [null],
            'DateTimeInterface' => [new DateTime('+1 DAY')],
            'string' => ['7 DAYS'],
            'int' => [86000],
        ];
    }

    public function testSettingTtlTrowsException()
    {
        self::expectException(TypeError::class);
        new Manager(new Cache(), new CurlHttpClient(), tmpfile());
    }
}
