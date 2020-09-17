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
use Pdp\TopLevelDomains;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use TypeError;
use function dirname;
use function json_encode;

/**
 * @coversDefaultClass \Pdp\Storage\RootZoneDatabaseCachePsr16Adapter
 */
final class RootZoneDatabasePsr16AdapterTest extends TestCase
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

        $pslCache = new RootZoneDatabaseCachePsr16Adapter($cache, '1 DAY');

        self::assertNull($pslCache->fetchByUri('http://www.example.com'));
    }

    public function testItReturnsAnInstanceIfTheCorrectCacheExists(): void
    {
        $cache = new class() implements CacheInterface {
            public function get($key, $default = null)
            {
                return json_encode(TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt'));
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

        $pslCache = new RootZoneDatabaseCachePsr16Adapter($cache, 86400);

        self::assertEquals(
            TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt'),
            $pslCache->fetchByUri('http://www.example.com')
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

        $pslCache = new RootZoneDatabaseCachePsr16Adapter($cache, new \DateInterval('P1D'), $logger);
        self::assertNull($pslCache->fetchByUri('http://www.example.com'));
        self::assertSame('Failed to JSON decode the string: Syntax error.', $logger->logs()[0]);
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

        $pslCache = new RootZoneDatabaseCachePsr16Adapter($cache, new \DateTimeImmutable('+1 DAY'), $logger);
        self::assertNull($pslCache->fetchByUri('http://www.example.com'));
        self::assertSame(
            'The decoded hashmap structure is missing at least one of the required properties: `records`, `version` and/or `modifiedDate`.',
            $logger->logs()[0]
        );
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

        new RootZoneDatabaseCachePsr16Adapter($cache, []);
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

        new RootZoneDatabaseCachePsr16Adapter($cache, 'foobar');
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

        $rzd = TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
        $pslCache = new RootZoneDatabaseCachePsr16Adapter($cache, new \DateInterval('P1D'), $logger);

        self::assertTrue($pslCache->storeByUri('http://www.example.com', $rzd));
        self::assertSame('The content associated with URI: `http://www.example.com` was stored.', $logger->logs()[0]);
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

        $rzd = TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
        $pslCache = new RootZoneDatabaseCachePsr16Adapter($cache, new \DateInterval('P1D'), $logger);

        self::assertFalse($pslCache->storeByUri('http://www.example.com', $rzd));
        self::assertSame('The content associated with URI: `http://www.example.com` could not be stored.', $logger->logs()[0]);
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

        $rzd = TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
        $pslCache = new RootZoneDatabaseCachePsr16Adapter($cache, new \DateInterval('P1D'), $logger);

        self::assertFalse($pslCache->storeByUri('http://www.example.com', $rzd));
        self::assertSame('The content associated with URI: `http://www.example.com` could not be cached: Something went wrong.', $logger->logs()[0]);
    }
}
