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
use Pdp\Exception\CouldNotLoadRules;
use Pdp\Exception\CouldNotLoadTLDs;
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
    /**
     * @var Cache
     */
    protected $cachePool;

    /**
     * @var string
     */
    protected $cacheDir;
    /**
     * @var \org\bovigo\vfs\vfsStreamDirectory
     */
    protected $root;

    /**
     * @var HttpClient
     */
    protected $client;

    public function setUp(): void
    {
        $this->root = vfsStream::setup('pdp');
        vfsStream::create(['cache' => []], $this->root);
        $this->cacheDir = vfsStream::url('pdp/cache');
        $this->cachePool = new Cache($this->cacheDir);
        $this->client = new class() implements HttpClient {
            public function getContent(string $url): string
            {
                if ($url === Manager::PSL_URL) {
                    /** @var string $res */
                    $res = file_get_contents(__DIR__.'/data/public_suffix_list.dat');

                    return $res;
                }

                if ($url === Manager::RZD_URL) {
                    /** @var string $res */
                    $res = file_get_contents(__DIR__.'/data/tlds-alpha-by-domain.txt');

                    return $res;
                }

                return '';
            }
        };
    }

    public function tearDown(): void
    {
        unset($this->cachePool, $this->cacheDir, $this->root, $this->client);
    }

    /**
     * @dataProvider validTtlProvider
     * @covers ::__construct
     * @covers ::filterTtl
     * @param mixed $ttl
     */
    public function testConstructor($ttl): void
    {
        self::assertInstanceOf(Manager::class, new Manager($this->cachePool, $this->client, $ttl));
    }

    public function validTtlProvider(): iterable
    {
        return [
            'DateInterval' => [new DateInterval('PT1H')],
            'null' => [null],
            'DateTimeInterface' => [new DateTime('+1 DAY')],
            'string' => ['7 DAYS'],
            'int' => [86000],
        ];
    }

    /**
     * @covers ::__construct
     * @covers ::filterTtl
     */
    public function testConstructorThrowsException(): void
    {
        self::expectException(TypeError::class);
        new Manager($this->cachePool, $this->client, tmpfile());
    }

    /**
     * @covers ::getRules
     * @covers ::getCacheKey
     * @covers ::refreshRules
     */
    public function testRefreshRules(): void
    {
        $manager = new Manager($this->cachePool, $this->client);
        $previous = $manager->getRules();
        self::assertTrue($manager->refreshRules());
        self::assertEquals($previous, $manager->getRules());
    }

    /**
     * @covers ::getRules
     * @covers ::getCacheKey
     * @covers ::refreshRules
     */
    public function testRebuildRulesFromRemoveSource(): void
    {
        $manager = new Manager($this->cachePool, $this->client);
        $previous = $manager->getRules(Manager::PSL_URL);
        $this->cachePool->clear(); //delete all local cache
        $list = $manager->getRules(Manager::PSL_URL);
        self::assertEquals($previous, $manager->getRules(Manager::PSL_URL));
    }

    /**
     * @covers ::getRules
     * @covers ::getCacheKey
     * @covers ::refreshRules
     */
    public function testGetRulesThrowsExceptionIfNotCacheCanBeRetrieveOrRefresh(): void
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
        $manager = new Manager($cachePool, $this->client);
        $manager->getRules('https://google.com');
    }

    /**
     * @covers ::getRules
     * @covers ::getCacheKey
     * @covers ::refreshRules
     */
    public function testGetRulesThrowsExceptionIfTheCacheIsCorrupted(): void
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
        $manager = new Manager($cachePool, $this->client);
        $manager->getRules();
    }

    /**
     * @covers ::getTLDs
     * @covers ::getCacheKey
     * @covers ::refreshTLDs
     */
    public function testRefreshTLDs(): void
    {
        $manager = new Manager($this->cachePool, $this->client);
        $previous = $manager->getTLDs();
        self::assertTrue($manager->refreshTLDs());
        self::assertEquals($previous, $manager->getTLDs());
    }

    /**
     * @covers ::getTLDs
     * @covers ::getCacheKey
     * @covers ::refreshTLDs
     */
    public function testRebuildTLDsFromRemoveSource(): void
    {
        $manager = new Manager($this->cachePool, $this->client);
        $previous = $manager->getTLDs();
        $this->cachePool->clear(); //delete all local cache
        self::assertEquals($previous, $manager->getTLDs());
    }

    /**
     * @covers ::getTLDs
     * @covers ::getCacheKey
     * @covers ::refreshTLDs
     */
    public function testGetTLDsThrowsExceptionIfNotCacheCanBeRetrieveOrRefresh(): void
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
        $manager = new Manager($cachePool, $this->client);
        $manager->getTLDs();
    }

    /**
     * @covers ::getTLDs
     * @covers ::getCacheKey
     * @covers ::refreshTLDs
     */
    public function testGetTLDsThrowsExceptionIfTheCacheIsCorrupted(): void
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
        $manager = new Manager($cachePool, $this->client);
        $manager->getTLDs();
    }

    /**
     * @covers ::getTLDs
     * @covers ::getCacheKey
     * @covers ::refreshTLDs
     */
    public function testGetTLDsThrowsExceptionIfTheCacheContentIsCorrupted(): void
    {
        $cachePool = new class() implements CacheInterface {
            public function get($key, $default = null)
            {
                return '{"foo":"bar"}'; //invalid Json
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
        $manager = new Manager($cachePool, $this->client);
        $manager->getTLDs();
    }
}
