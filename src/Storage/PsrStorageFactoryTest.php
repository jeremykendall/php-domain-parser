<?php

declare(strict_types=1);

namespace Pdp\Storage;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;

final class PsrStorageFactoryTest extends TestCase
{
    private PsrStorageFactory $factory;

    public function setUp(): void
    {
        $cache = self::createStub(CacheInterface::class);
        $requestFactory = self::createStub(RequestFactoryInterface::class);
        $client = self::createStub(ClientInterface::class);

        $this->factory = new PsrStorageFactory($cache, $client, $requestFactory);
    }

    public function testItCanReturnARootZoneDatabaseStorageInstance(): void
    {
        $instance = $this->factory->createTopLevelDomainListStorage('foobar', '1 DAY');

        self::assertInstanceOf(TopLevelDomainsStorage::class, $instance);
    }

    public function testItCanReturnAPublicSuffixListStorageInstance(): void
    {
        $instance = $this->factory->createPublicSuffixListStorage('foobar', '1 DAY');

        self::assertInstanceOf(RulesStorage::class, $instance);
    }
}
