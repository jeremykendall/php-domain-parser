<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Pdp\Storage;

use Pdp\Rules;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use function dirname;

/**
 * @coversDefaultClass \Pdp\Storage\RulesPsr16Cache
 */
final class RulesPsr16CacheTest extends TestCase
{
    public function testItReturnsNullIfTheCacheDoesNotExists(): void
    {
        $cache = new class() implements CacheInterface {
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

        $pslCache = new RulesPsr16Cache($cache, 'pdp_', '1 DAY');

        self::assertNull($pslCache->fetch('http://www.example.com'));
    }

    public function testItReturnsAnInstanceIfTheCorrectCacheExists(): void
    {
        $cache = new class() implements CacheInterface {
            public function get($key, $default = null)
            {
                return Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
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

        $pslCache = new RulesPsr16Cache($cache, 'pdp_', 86400);

        self::assertEquals(
            Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat'),
            $pslCache->fetch('http://www.example.com')
        );
    }

    public function testItReturnsNullIfTheCacheContentContainsInvalidJsonData(): void
    {
        $cache = new class() implements CacheInterface {
            public function get($key, $default = null)
            {
                return 'foobar';
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

        $pslCache = new RulesPsr16Cache($cache, 'pdp_', 86400);
        self::assertNull($pslCache->fetch('http://www.example.com'));
    }

    public function testItReturnsNullIfTheCacheContentCannotBeConvertedToTheCorrectInstance(): void
    {
        $cache = new class() implements CacheInterface {
            public function get($key, $default = null)
            {
                return '{"foo":"bar"}';
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

        $pslCache = new RulesPsr16Cache($cache, 'pdp_', new \DateTimeImmutable('+1 DAY'));

        self::assertNull($pslCache->fetch('http://www.example.com'));
    }

    public function testItCanStoreAPublicSuffixListInstance(): void
    {
        $cache = new class() implements CacheInterface {
            public function get($key, $default = null)
            {
                return null;
            }

            public function set($key, $value, $ttl = null)
            {
                return true;
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

        $psl = Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
        $pslCache = new RulesPsr16Cache($cache, 'pdp_', new \DateInterval('P1D'));

        self::assertTrue($pslCache->store('http://www.example.com', $psl));
    }

    public function testItReturnsFalseIfItCantStoreAPublicSuffixListInstance(): void
    {
        $cache = new class() implements CacheInterface {
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

        $psl = Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
        $pslCache = new RulesPsr16Cache($cache, 'pdp_', new \DateInterval('P1D'));

        self::assertFalse($pslCache->store('http://www.example.com', $psl));
    }


    public function testItReturnsFalseIfItCantCacheAPublicSuffixListInstance(): void
    {
        $cache = new class() implements CacheInterface {
            public function get($key, $default = null)
            {
                return null;
            }

            public function set($key, $value, $ttl = null)
            {
                throw new class('Something went wrong.', 0) extends RuntimeException implements CacheException {
                };
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

        $psl = Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
        $pslCache = new RulesPsr16Cache($cache, 'pdp_', new \DateInterval('P1D'));

        self::assertFalse($pslCache->store('http://www.example.com', $psl));
    }
}
