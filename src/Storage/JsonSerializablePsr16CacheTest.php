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

namespace Pdp\Storage;

use InvalidArgumentException;
use JsonSerializable;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use TypeError;

/**
 * @coversDefaultClass \Pdp\Storage\JsonSerializablePsr16Cache
 */
final class JsonSerializablePsr16CacheTest extends TestCase
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

        $cache = new JsonSerializablePsr16Cache('pdp_', $cache, '1 DAY');
        $jsonInstance = new class() implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['foo' => 'bar'];
            }
        };

        self::assertFalse($cache->store('http://www.example.com', $jsonInstance));
    }

    public function testItReturnsAJsonStringIfTheCacheExists(): void
    {
        $cache = new class() implements CacheInterface {
            private array $data = [];

            public function get($key, $default = null)
            {
                return $this->data[$key] ?? $default;
            }

            public function set($key, $value, $ttl = null)
            {
                $this->data[$key] = $value;

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

        $jsonInstance = new class() implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['foo' => 'bar'];
            }
        };

        $cache = new JsonSerializablePsr16Cache('pdp_', $cache, 86400);
        $cache->store('http://www.example.com', $jsonInstance);

        self::assertEquals('{"foo":"bar"}', $cache->fetch('http://www.example.com'));
    }

    public function testItThrowsOnConstructionIfTheTTLIsNotTheCorrectType(): void
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

        self::expectException(TypeError::class);

        new JsonSerializablePsr16Cache('pdp_', $cache, []);
    }

    public function testItThrowsOnConstructionIfTheTTLStringCanNotBeParsed(): void
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

        self::expectException(InvalidArgumentException::class);

        new JsonSerializablePsr16Cache('pdp_', $cache, 'foobar');
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

        $logger = new class() extends AbstractLogger {
            private array $logs = [];

            public function log($level, $message, array $context = [])
            {
                $replace = [];
                foreach ($context as $key => $val) {
                    $replace['{'.$key.'}'] = $val;
                }

                $this->logs[] = strtr($message, $replace);
            }

            public function logs(): array
            {
                return $this->logs;
            }
        };

        $jsonInstance = new class() implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['foo' => 'bar'];
            }
        };
        $cache = new JsonSerializablePsr16Cache('pdp_', $cache, new \DateInterval('P1D'), $logger);

        self::assertTrue($cache->store('http://www.example.com', $jsonInstance));
        self::assertSame('The content associated with: `http://www.example.com` was stored.', $logger->logs()[0]);
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

        $logger = new class() extends AbstractLogger {
            private array $logs = [];

            public function log($level, $message, array $context = [])
            {
                $replace = [];
                foreach ($context as $key => $val) {
                    $replace['{'.$key.'}'] = $val;
                }

                $this->logs[] = strtr($message, $replace);
            }

            public function logs(): array
            {
                return $this->logs;
            }
        };

        $jsonInstance = new class() implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['foo' => 'bar'];
            }
        };

        $cache = new JsonSerializablePsr16Cache('pdp_', $cache, new \DateTimeImmutable('+1 DAY'), $logger);

        self::assertFalse($cache->store('http://www.example.com', $jsonInstance));
        self::assertSame('The content associated with: `http://www.example.com` could not be stored.', $logger->logs()[0]);
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

        $logger = new class() extends AbstractLogger {
            private array $logs = [];

            public function log($level, $message, array $context = [])
            {
                $replace = [];
                foreach ($context as $key => $val) {
                    $replace['{'.$key.'}'] = $val;
                }

                $this->logs[] = strtr($message, $replace);
            }

            public function logs(): array
            {
                return $this->logs;
            }
        };

        $jsonInstance = new class() implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['foo' => 'bar'];
            }
        };

        $cache = new JsonSerializablePsr16Cache('pdp_', $cache, new \DateInterval('P1D'), $logger);

        self::assertFalse($cache->store('http://www.example.com', $jsonInstance));
        self::assertSame('The content associated with: `http://www.example.com` could not be cached: Something went wrong.', $logger->logs()[0]);
    }
}
