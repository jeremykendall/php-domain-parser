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

use Pdp\RootZoneDatabase;
use Pdp\TopLevelDomains;
use PHPUnit\Framework\TestCase;
use function dirname;

/**
 * @coversDefaultClass \Pdp\Storage\TopLevelDomainsRepository
 */
final class TopLevelDomainsRepositoryTest extends TestCase
{
    public function testIsCanReturnARootZoneDatabaseInstanceFromCache(): void
    {
        $cache = new class() implements RootZoneDatabaseCache {
            public function fetchByUri(string $uri): ?RootZoneDatabase
            {
                return TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
            }

            public function storeByUri(string $uri, RootZoneDatabase $topLevelDomains): bool
            {
                return true;
            }
        };

        $client = new class() implements RootZoneDatabaseRepository {
            public function getByUri(string $uri): RootZoneDatabase
            {
                return TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
            }
        };

        $storage = new TopLevelDomainsRepository($client, $cache);

        self::assertInstanceOf(TopLevelDomains::class, $storage->getByUri('http://www.example.com'));
    }

    public function testIsCanReturnARootZoneDatabaseInstanceFromTheInnerStorage(): void
    {
        $cache = new class() implements RootZoneDatabaseCache {
            public function fetchByUri(string $uri): ?RootZoneDatabase
            {
                return null;
            }

            public function storeByUri(string $uri, RootZoneDatabase $publicSuffixList): bool
            {
                return true;
            }
        };

        $client = new class() implements RootZoneDatabaseRepository {
            public function getByUri(string $uri): RootZoneDatabase
            {
                return TopLevelDomains::fromPath(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');
            }
        };

        $storage = new TopLevelDomainsRepository($client, $cache);

        self::assertInstanceOf(TopLevelDomains::class, $storage->getByUri('http://www.example.com'));
    }
}
