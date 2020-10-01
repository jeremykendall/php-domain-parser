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

use Pdp\RootZoneDatabase;
use Pdp\TopLevelDomains;
use PHPUnit\Framework\TestCase;
use function dirname;

/**
 * @coversDefaultClass \Pdp\Storage\TopLevelDomainsStorage
 */
final class TopLevelDomainsStorageTest extends TestCase
{
    public function testIsCanReturnARootZoneDatabaseInstanceFromCache(): void
    {
        $cache = new class() implements RootZoneDatabaseCache {
            public function fetch(string $uri): ?RootZoneDatabase
            {
                return TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
            }

            public function store(string $uri, RootZoneDatabase $rootZoneDatabase): bool
            {
                return true;
            }

            public function forget(string $uri): bool
            {
                return true;
            }
        };

        $client = new class() implements RootZoneDatabaseClient {
            public function get(string $uri): RootZoneDatabase
            {
                return TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
            }
        };

        $storage = new TopLevelDomainsStorage($client, $cache);

        self::assertInstanceOf(TopLevelDomains::class, $storage->get('http://www.example.com'));
    }

    public function testIsCanReturnARootZoneDatabaseInstanceFromTheInnerStorage(): void
    {
        $cache = new class() implements RootZoneDatabaseCache {
            public function fetch(string $uri): ?RootZoneDatabase
            {
                return null;
            }

            public function store(string $uri, RootZoneDatabase $publicSuffixList): bool
            {
                return true;
            }

            public function forget(string $uri): bool
            {
                return true;
            }
        };

        $client = new class() implements RootZoneDatabaseClient {
            public function get(string $uri): RootZoneDatabase
            {
                return TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
            }
        };

        $storage = new TopLevelDomainsStorage($client, $cache);

        self::assertInstanceOf(TopLevelDomains::class, $storage->get('http://www.example.com'));
    }
}
