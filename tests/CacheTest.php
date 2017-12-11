<?php

declare(strict_types=1);

namespace pdp\tests;

use DateInterval;
use org\bovigo\vfs\vfsStream;
use Pdp\Cache;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;
use Traversable;

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

    /**
     * @dataProvider storableValuesProvider
     *
     * @param mixed  $expected
     * @param string $key
     */
    public function testSetGet($expected)
    {
        $this->cache->set('foo', $expected);
        $this->assertEquals($expected, $this->cache->get('foo'));
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
        $this->assertEquals('bar', $this->cache->get('foo'));
        $this->cache->delete('foo');
        $this->assertNull($this->cache->get('foo'));
    }

    public function testGetInvalidArg()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->get(null);
    }

    public function testInvalidKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->get('foo:bar', 'bar');
    }

    public function testSetInvalidTTL()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->set('foo', 'bar', date_create());
    }

    /**
     * @depends testDelete
     */
    public function testGetNotFound()
    {
        $this->assertNull($this->cache->get('notfound'));
    }

    /**
     * @depends testDelete
     */
    public function testGetNotFoundDefault()
    {
        $default = 'chickpeas';
        $this->assertEquals(
            $default,
            $this->cache->get('notfound', $default)
        );
    }

    /**
     * @depends testSetGet
     * @slow
     */
    public function testSetExpire()
    {
        $this->cache->set('foo', 'bar', 1);
        $this->assertEquals('bar', $this->cache->get('foo'));

        // Wait 2 seconds so the cache expires
        usleep(2000000);
        $this->assertNull($this->cache->get('foo'));
    }

    /**
     * @depends testSetGet
     * @slow
     */
    public function testSetExpireDateInterval()
    {
        $this->cache->set('foo', 'bar', new DateInterval('PT1S'));
        $this->assertEquals('bar', $this->cache->get('foo'));

        // Wait 2 seconds so the cache expires
        usleep(2000000);
        $this->assertNull($this->cache->get('foo'));
    }

    public function testSetInvalidArg()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->set(null, 'bar');
    }

    public function testDeleteInvalidArg()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->delete(null);
    }

    /**
     * @depends testSetGet
     */
    public function testClearCache()
    {
        $this->cache->set('foo', 'bar');
        $this->cache->clear();
        $this->assertNull($this->cache->get('foo'));
    }

    /**
     * @depends testSetGet
     */
    public function testHas()
    {
        $this->cache->set('foo', 'bar');
        $this->assertTrue($this->cache->has('foo'));
    }

    /**
     * @depends testHas
     */
    public function testHasNot()
    {
        $this->assertFalse($this->cache->has('not-found'));
    }

    public function testHasInvalidArg()
    {
        $this->expectException(InvalidArgumentException::class);
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
            $this->assertTrue(isset($values[$key]));
            $this->assertEquals($values[$key], $value);
            unset($values[$key]);
        }

        // The list of values should now be empty
        $this->assertEquals([], $values);
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
            $this->assertTrue(isset($values[$key]));
            $this->assertEquals($values[$key], $value);
            unset($values[$key]);
        }

        // The list of values should now be empty
        $this->assertEquals([], $values);
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
            $this->assertTrue(isset($values[$key]));
            $this->assertEquals($values[$key], $value);
            unset($values[$key]);
        }

        // The list of values should now be empty
        $this->assertEquals([], $values);
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
            $this->assertTrue(isset($values[$key]));
            $this->assertEquals($values[$key], $value);
            unset($values[$key]);
        }
        $this->assertEquals(3, $count);
        // The list of values should now be empty
        $this->assertEquals([], $values);
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
            $this->assertTrue(isset($expected[$key]));
            $this->assertEquals($expected[$key], $value);
            unset($expected[$key]);
        }
        $this->assertEquals(3, $count);

        // The list of values should now be empty
        $this->assertEquals([], $expected);
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
            $this->assertTrue(isset($expected[$key]));
            $this->assertEquals($expected[$key], $value);
            unset($expected[$key]);
        }
        $this->assertEquals(3, $count);

        // The list of values should now be empty
        $this->assertEquals([], $expected);
    }

    public function testSetMultipleInvalidArg()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->setMultiple(null);
    }

    public function testGetMultipleInvalidArg()
    {
        $this->expectException(InvalidArgumentException::class);
        $result = $this->cache->getMultiple(null);
        // If $result was a generator, the generator will only error once the
        // first value is requested.
        //
        // This extra line is just a precaution for that
        if ($result instanceof Traversable) {
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
            $this->assertTrue(isset($expected[$key]));
            $this->assertEquals($expected[$key], $value);
            unset($expected[$key]);
        }

        // The list of values should now be empty
        $this->assertEquals([], $expected);
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
            $this->assertTrue(isset($expected[$key]));
            $this->assertEquals($expected[$key], $value);
            unset($expected[$key]);
        }

        // The list of values should now be empty
        $this->assertEquals([], $expected);
    }

    /**
     * @expectException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testDeleteMultipleInvalidArg()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->deleteMultiple(null);
    }
}
