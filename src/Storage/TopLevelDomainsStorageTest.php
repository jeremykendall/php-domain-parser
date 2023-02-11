<?php

declare(strict_types=1);

namespace Pdp\Storage;

use Pdp\TopLevelDomainList;
use Pdp\TopLevelDomains;
use PHPUnit\Framework\TestCase;
use function dirname;

final class TopLevelDomainsStorageTest extends TestCase
{
    public function testIsCanReturnARootZoneDatabaseInstanceFromCache(): void
    {
        $cache = new class() implements TopLevelDomainListCache {
            public function fetch(string $uri): ?TopLevelDomainList
            {
                return TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
            }

            public function remember(string $uri, TopLevelDomainList $topLevelDomainList): bool
            {
                return true;
            }

            public function forget(string $uri): bool
            {
                return true;
            }
        };

        $client = new class() implements TopLevelDomainListClient {
            public function get(string $uri): TopLevelDomainList
            {
                return TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
            }
        };

        $storage = new TopLevelDomainsStorage($cache, $client);

        self::assertInstanceOf(TopLevelDomains::class, $storage->get('http://www.example.com'));
    }

    public function testIsCanReturnARootZoneDatabaseInstanceFromTheInnerStorage(): void
    {
        $cache = new class() implements TopLevelDomainListCache {
            public function fetch(string $uri): ?TopLevelDomainList
            {
                return null;
            }

            public function remember(string $uri, TopLevelDomainList $topLevelDomainList): bool
            {
                return true;
            }

            public function forget(string $uri): bool
            {
                return true;
            }
        };

        $client = new class() implements TopLevelDomainListClient {
            public function get(string $uri): TopLevelDomainList
            {
                return TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
            }
        };

        $storage = new TopLevelDomainsStorage($cache, $client);

        self::assertInstanceOf(TopLevelDomains::class, $storage->get('http://www.example.com'));
    }

    public function testIsCanDeleteARootZoneDatabaseInstanceFromTheInnerStorage(): void
    {
        $cache = new class() implements TopLevelDomainListCache {
            public function fetch(string $uri): ?TopLevelDomainList
            {
                return null;
            }

            public function remember(string $uri, TopLevelDomainList $topLevelDomainList): bool
            {
                return true;
            }

            public function forget(string $uri): bool
            {
                return 'http://www.example.com' === $uri;
            }
        };

        $client = new class() implements TopLevelDomainListClient {
            public function get(string $uri): TopLevelDomainList
            {
                return TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
            }
        };

        $storage = new TopLevelDomainsStorage($cache, $client);
        $storage->get('http://www.example.com');

        self::assertTrue($storage->delete('http://www.example.com'));
        self::assertFalse($storage->delete('https://www.example.com'));
    }
}
