<?php

declare(strict_types=1);

namespace Pdp\Storage;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Pdp\TopLevelDomains;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use stdClass;
use TypeError;
use function dirname;

/**
 * @coversDefaultClass \Pdp\Storage\TopLevelDomainListPsr16Cache
 */
final class TopLevelDomainListPsr16CacheTest extends TestCase
{
    public function testItReturnsNullIfTheCacheDoesNotExists(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn(null);

        $instance = new TopLevelDomainListPsr16Cache($cache, 'pdp_', '1 DAY');

        self::assertNull($instance->fetch('http://www.example.com'));
    }

    public function testItReturnsAnInstanceIfTheCorrectCacheExists(): void
    {
        $topLevelDomainList = TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn($topLevelDomainList);

        $instance = new TopLevelDomainListPsr16Cache($cache, 'pdp_', 86400);

        self::assertEquals($topLevelDomainList, $instance->fetch('http://www.example.com'));
    }

    public function testItReturnsNullIfTheCacheContentContainsInvalidJsonData(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn('foobar');

        $instance = new TopLevelDomainListPsr16Cache($cache, 'pdp_', new DateInterval('P1D'));

        self::assertNull($instance->fetch('http://www.example.com'));
    }

    public function testItReturnsNullIfTheCacheContentCannotBeConvertedToTheCorrectInstance(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn('{"foo":"bar"}');

        $instance = new TopLevelDomainListPsr16Cache($cache, 'pdp_', new DateTimeImmutable('+1 DAY'));

        self::assertNull($instance->fetch('http://www.example.com'));
    }

    public function testItCanStoreAPublicSuffixListInstance(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('set')->willReturn(true);

        $rzd = TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
        $instance = new TopLevelDomainListPsr16Cache($cache, 'pdp_', new DateInterval('P1D'));

        self::assertTrue($instance->remember('http://www.example.com', $rzd));
    }

    public function testItReturnsFalseIfItCantStoreAPublicSuffixListInstance(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('set')->willReturn(false);

        $rzd = TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
        $instance = new TopLevelDomainListPsr16Cache($cache, 'pdp_', new DateInterval('P1D'));

        self::assertFalse($instance->remember('http://www.example.com', $rzd));
    }

    public function testItReturnsFalseIfItCantCacheATopLevelDomainListInstance(): void
    {
        $exception = new class('Something went wrong.', 0) extends RuntimeException implements CacheException {
        };
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('set')->will(self::throwException($exception));

        $rzd = TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
        $instance = new TopLevelDomainListPsr16Cache($cache, 'pdp_', new DateInterval('P1D'));

        self::assertFalse($instance->remember('http://www.example.com', $rzd));
    }

    public function testItThrowsIfItCantCacheATopLevelDomainListInstance(): void
    {
        $exception = new class('Something went wrong.', 0) extends RuntimeException {
        };
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('set')->will(self::throwException($exception));

        $rzd = TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
        $instance = new TopLevelDomainListPsr16Cache($cache, 'pdp_', new DateInterval('P1D'));

        $this->expectException(RuntimeException::class);

        $instance->remember('http://www.example.com', $rzd);
    }

    public function testItCanDeleteTheCachedDatabase(): void
    {
        $uri = 'http://www.example.com';

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('delete')->willReturn(true);

        $instance = new TopLevelDomainListPsr16Cache($cache, 'pdp_', new DateInterval('P1D'));
        self::assertTrue($instance->forget($uri));
    }

    public function testItWillThrowIfTheTTLIsNotParsable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $cache = $this->createStub(CacheInterface::class);
        new TopLevelDomainListPsr16Cache($cache, 'pdp_', 'foobar');
    }

    public function testItWillThrowIfTheTTLIsInvalid(): void
    {
        $this->expectException(TypeError::class);

        $cache = $this->createStub(CacheInterface::class);
        new TopLevelDomainListPsr16Cache($cache, 'pdp_', new stdClass());
    }
}
