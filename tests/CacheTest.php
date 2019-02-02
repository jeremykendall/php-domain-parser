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
use Iterator;
use org\bovigo\vfs\vfsStream;
use Pdp\Cache;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Abstract PSR-16 tester.
 *
 * Because all cache implementations should mostly behave the same way, they
 * can all extend this test.
 *
 * This test suite is heavily borrowed from Sabre Cache Test Suite
 *
 * @see https://github.com/fruux/sabre-cache/blob/master/tests/AbstractCacheTest.php
 */
class CacheTest extends TestCase
{
    protected $cache;

    protected $root;

    protected $cacheDir;

    public function setUp()
    {
        $this->root = vfsStream::setup('pdp');
        vfsStream::create(['cache' => []], $this->root);
        $this->cacheDir = vfsStream::url('pdp/cache');
        $this->cache = new Cache($this->cacheDir);
    }

    public function tearDown()
    {
        $this->cache = null;
        $this->cacheDir = null;
        $this->root = null;
    }

    public function testConstructorOnEmptyCachePath()
    {
        $cache = new Cache('');
        self::assertNull($cache->get('invalid_key'));
    }

    public function testConstructorOnParentCachePathIsNotExisted()
    {
        $cache = new Cache(vfsStream::url('pdp/cache_not_exist'));
        self::assertNull($cache->get('invalid_key'));
    }

    public function testSetOnNotWritableCachePath()
    {
        self::expectException(\InvalidArgumentException::class);
        $cache = new Cache('/bin');
    }

    public function testSetOnNotExistingCachePath()
    {
        self::expectException(\InvalidArgumentException::class);
        $cache = new Cache('/foo/bar');
    }

    /**
     * @dataProvider storableValuesProvider
     *
     * @param mixed $expected
     */
    public function testSetGet($expected)
    {
        $this->cache->set('foo', $expected);
        self::assertEquals($expected, $this->cache->get('foo'));
    }

    public function storableValuesProvider()
    {
        return [
            'string' => ['bar'],
            'boolean' => [false],
            'array' => [['foo', 'bar']],
            'class' => [date_create()],
            'null' => [null],
            'float' => [1.1],
        ];
    }

    /**
     * @depends testSetGet
     */
    public function testDelete()
    {
        $this->cache->set('foo', 'bar');
        self::assertEquals('bar', $this->cache->get('foo'));
        $this->cache->delete('foo');
        self::assertNull($this->cache->get('foo'));
    }

    public function testGetInvalidArg()
    {
        self::expectException(InvalidArgumentException::class);
        $this->cache->get(null);
    }

    public function testInvalidKey()
    {
        self::expectException(InvalidArgumentException::class);
        $this->cache->get('foo:bar', 'bar');
    }

    public function testSetInvalidTTL()
    {
        self::expectException(InvalidArgumentException::class);
        $this->cache->set('foo', 'bar', date_create());
    }

    /**
     * @depends testDelete
     */
    public function testGetNotFound()
    {
        self::assertNull($this->cache->get('notfound'));
    }

    /**
     * @depends testDelete
     */
    public function testGetNotFoundDefault()
    {
        $expected = 'chickpeas';
        self::assertEquals($expected, $this->cache->get('notfound', $expected));
    }

    /**
     * @depends testSetGet
     * @slow
     */
    public function testSetExpire()
    {
        $this->cache->set('foo', 'bar', 1);
        self::assertEquals('bar', $this->cache->get('foo'));

        // Wait 3 seconds so the cache expires
        sleep(3);
        self::assertNull($this->cache->get('foo'));
    }

    /**
     * @depends testSetGet
     * @slow
     */
    public function testSetExpireDateInterval()
    {
        $this->cache->set('foo', 'bar', new DateInterval('PT1S'));
        self::assertEquals('bar', $this->cache->get('foo'));

        // Wait 3 seconds so the cache expires
        sleep(3);
        self::assertNull($this->cache->get('foo'));
    }

    public function testSetInvalidArg()
    {
        self::expectException(InvalidArgumentException::class);
        $this->cache->set(null, 'bar');
    }

    public function testDeleteInvalidArg()
    {
        self::expectException(InvalidArgumentException::class);
        $this->cache->delete(null);
    }

    /**
     * @depends testSetGet
     */
    public function testClearCache()
    {
        $this->cache->set('foo', 'bar');
        $this->cache->clear();
        self::assertNull($this->cache->get('foo'));
    }

    /**
     * @depends testSetGet
     */
    public function testHas()
    {
        $this->cache->set('foo', 'bar');
        self::assertTrue($this->cache->has('foo'));
    }

    /**
     * @depends testHas
     */
    public function testHasNot()
    {
        self::assertFalse($this->cache->has('not-found'));
    }

    public function testHasInvalidArg()
    {
        self::expectException(InvalidArgumentException::class);
        $this->cache->has(null);
    }

    /**
     * @depends testSetGet
     */
    public function testSetGetMultiple()
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->cache->setMultiple($values);
        $result = $this->cache->getMultiple(array_keys($values));
        foreach ($result as $key => $value) {
            self::assertTrue(isset($values[$key]));
            self::assertEquals($values[$key], $value);
            unset($values[$key]);
        }

        // The list of values should now be empty
        self::assertEquals([], $values);
    }

    /**
     * @depends testSetGet
     */
    public function testSetGetMultipleGenerator()
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $gen = function () use ($values) {
            foreach ($values as $key => $value) {
                yield $key => $value;
            }
        };

        $this->cache->setMultiple($gen());

        $result = $this->cache->getMultiple(array_keys($values));
        foreach ($result as $key => $value) {
            self::assertTrue(isset($values[$key]));
            self::assertEquals($values[$key], $value);
            unset($values[$key]);
        }

        // The list of values should now be empty
        self::assertEquals([], $values);
    }

    /**
     * @depends testSetGet
     */
    public function testSetGetMultipleGenerator2()
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $gen = function () use ($values) {
            foreach ($values as $key => $value) {
                yield $key;
            }
        };

        $this->cache->setMultiple($values);
        $result = $this->cache->getMultiple($gen());
        foreach ($result as $key => $value) {
            self::assertTrue(isset($values[$key]));
            self::assertEquals($values[$key], $value);
            unset($values[$key]);
        }

        // The list of values should now be empty
        self::assertEquals([], $values);
    }

    /**
     * @depends testSetGetMultiple
     * @depends testSetExpire
     * @slow
     */
    public function testSetMultipleExpireDateIntervalNotExpired()
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->cache->setMultiple($values, new DateInterval('PT5S'));
        $result = $this->cache->getMultiple(array_keys($values));
        $count = 0;
        foreach ($result as $key => $value) {
            ++$count;
            self::assertTrue(isset($values[$key]));
            self::assertEquals($values[$key], $value);
            unset($values[$key]);
        }
        self::assertEquals(3, $count);
        // The list of values should now be empty
        self::assertEquals([], $values);
    }

    /**
     * @slow
     */
    public function testSetMultipleExpireDateIntervalExpired()
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->cache->setMultiple($values, new \DateInterval('PT1S'));

        // Wait 2 seconds so the cache expires
        sleep(2);

        $result = $this->cache->getMultiple(array_keys($values), 'not-found');
        $count = 0;

        $expected = [
            'key1' => 'not-found',
            'key2' => 'not-found',
            'key3' => 'not-found',
        ];

        foreach ($result as $key => $value) {
            ++$count;
            self::assertTrue(isset($expected[$key]));
            self::assertEquals($expected[$key], $value);
            unset($expected[$key]);
        }
        self::assertEquals(3, $count);

        // The list of values should now be empty
        self::assertEquals([], $expected);
    }

    /**
     * @slow
     */
    public function testSetMultipleExpireDateIntervalInt()
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->cache->setMultiple($values, 1);

        // Wait 2 seconds so the cache expires
        sleep(2);

        $result = $this->cache->getMultiple(array_keys($values), 'not-found');
        $count = 0;

        $expected = [
            'key1' => 'not-found',
            'key2' => 'not-found',
            'key3' => 'not-found',
        ];

        foreach ($result as $key => $value) {
            ++$count;
            self::assertTrue(isset($expected[$key]));
            self::assertEquals($expected[$key], $value);
            unset($expected[$key]);
        }
        self::assertEquals(3, $count);

        // The list of values should now be empty
        self::assertEquals([], $expected);
    }

    public function testSetMultipleInvalidArg()
    {
        self::expectException(InvalidArgumentException::class);
        $this->cache->setMultiple(null);
    }

    public function testGetMultipleInvalidArg()
    {
        self::expectException(InvalidArgumentException::class);
        $result = $this->cache->getMultiple(null);
        // If $result was a generator, the generator will only error once the
        // first value is requested.
        //
        // This extra line is just a precaution for that
        if ($result instanceof Iterator) {
            $result->current();
        }
    }

    /**
     * @depends testSetGetMultiple
     */
    public function testDeleteMultipleDefaultGet()
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->cache->setMultiple($values);

        $this->cache->deleteMultiple(['key1', 'key3']);

        $result = $this->cache->getMultiple(array_keys($values), 'tea');

        $expected = [
            'key1' => 'tea',
            'key2' => 'value2',
            'key3' => 'tea',
        ];

        foreach ($result as $key => $value) {
            self::assertTrue(isset($expected[$key]));
            self::assertEquals($expected[$key], $value);
            unset($expected[$key]);
        }

        // The list of values should now be empty
        self::assertEquals([], $expected);
    }

    /**
     * @depends testSetGetMultiple
     */
    public function testDeleteMultipleGenerator()
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->cache->setMultiple($values);

        $gen = function () {
            yield 'key1';
            yield 'key3';
        };

        $this->cache->deleteMultiple($gen());

        $result = $this->cache->getMultiple(array_keys($values), 'tea');

        $expected = [
            'key1' => 'tea',
            'key2' => 'value2',
            'key3' => 'tea',
        ];

        foreach ($result as $key => $value) {
            self::assertTrue(isset($expected[$key]));
            self::assertEquals($expected[$key], $value);
            unset($expected[$key]);
        }

        // The list of values should now be empty
        self::assertEquals([], $expected);
    }

    public function testDeleteMultipleInvalidArg()
    {
        self::expectException(InvalidArgumentException::class);
        $this->cache->deleteMultiple(null);
    }
}
