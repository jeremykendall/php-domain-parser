<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Pdp\Rules;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use function dirname;

final class PublicSuffixListPsr16CacheTest extends TestCase
{
    public function testItReturnsNullIfTheCacheDoesNotExists(): void
    {
        $cache = self::createStub(CacheInterface::class);
        $cache->method('get')->willReturn(null);

        $pslCache = new PublicSuffixListPsr16Cache($cache, 'pdp_', '1 DAY');

        self::assertNull($pslCache->fetch('http://www.example.com'));
    }

    public function testItReturnsAnInstanceIfTheCorrectCacheExists(): void
    {
        $rules = Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
        $cache = self::createStub(CacheInterface::class);
        $cache->method('get')->willReturn($rules);

        $pslCache = new PublicSuffixListPsr16Cache($cache, 'pdp_', 86400);

        self::assertEquals($rules, $pslCache->fetch('http://www.example.com'));
    }

    public function testItReturnsNullIfTheCacheContentContainsInvalidJsonData(): void
    {
        $cache = self::createStub(CacheInterface::class);
        $cache->method('get')->willReturn('foobar');

        $pslCache = new PublicSuffixListPsr16Cache($cache, 'pdp_', 86400);
        self::assertNull($pslCache->fetch('http://www.example.com'));
    }

    public function testItReturnsNullIfTheCacheContentCannotBeConvertedToTheCorrectInstance(): void
    {
        $cache = self::createStub(CacheInterface::class);
        $cache->method('get')->willReturn('{"foo":"bar"}');

        $pslCache = new PublicSuffixListPsr16Cache($cache, 'pdp_', new DateTimeImmutable('+1 DAY'));

        self::assertNull($pslCache->fetch('http://www.example.com'));
    }

    public function testItCanStoreAPublicSuffixListInstance(): void
    {
        $cache = self::createStub(CacheInterface::class);
        $cache->method('set')->willReturn(true);

        $psl = Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
        $pslCache = new PublicSuffixListPsr16Cache($cache, 'pdp_', new DateInterval('P1D'));

        self::assertTrue($pslCache->remember('http://www.example.com', $psl));
    }

    public function testItReturnsFalseIfItCantStoreAPublicSuffixListInstance(): void
    {
        $cache = self::createStub(CacheInterface::class);
        $cache->method('set')->willReturn(false);

        $psl = Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
        $pslCache = new PublicSuffixListPsr16Cache($cache, 'pdp_', new DateInterval('P1D'));

        self::assertFalse($pslCache->remember('http://www.example.com', $psl));
    }

    public function testItReturnsFalseIfItCantCacheAPublicSuffixListInstance(): void
    {
        $exception = new class('Something went wrong.', 0) extends RuntimeException implements CacheException {
        };
        $cache = self::createStub(CacheInterface::class);
        $cache->method('set')->will(self::throwException($exception));

        $psl = Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
        $pslCache = new PublicSuffixListPsr16Cache($cache, 'pdp_', new DateInterval('P1D'));

        self::assertFalse($pslCache->remember('http://www.example.com', $psl));
    }

    public function testItWillThrowIfItCantCacheAPublicSuffixListInstance(): void
    {
        $exception = new class('Something went wrong.', 0) extends RuntimeException {
        };
        $cache = self::createStub(CacheInterface::class);
        $cache->method('set')->will(self::throwException($exception));

        $psl = Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
        $pslCache = new PublicSuffixListPsr16Cache($cache, 'pdp_', new class() {
            public function __toString(): string
            {
                return '1 DAY';
            }
        });
        $this->expectException(RuntimeException::class);

        $pslCache->remember('http://www.example.com', $psl);
    }

    public function testItCanDeleteTheCachedDatabase(): void
    {
        $uri = 'http://www.example.com';

        $cache = self::createStub(CacheInterface::class);
        $cache->method('delete')->willReturn(true);

        $instance = new PublicSuffixListPsr16Cache($cache, 'pdp_', new DateInterval('P1D'));
        self::assertTrue($instance->forget($uri));
    }

    public function testItWillThrowIfTheTTLIsNotParsable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $cache = self::createStub(CacheInterface::class);
        new PublicSuffixListPsr16Cache($cache, 'pdp_', 'foobar');
    }
}
