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

namespace Pdp\Tests\Storage;

use Pdp\RootZoneDatabase;
use Pdp\Storage\RootZoneDatabaseCache;
use Pdp\Storage\RootZoneDatabaseLocalStorage;
use Pdp\Storage\RootZoneDatabaseStorage;
use Pdp\TopLevelDomains;
use PHPUnit\Framework\TestCase;
use function dirname;

final class RootZoneDatabaseLocalStorageTest extends TestCase
{
    public function testIsCanReturnARootZoneDatabaseInstanceFromCache(): void
    {
        $cache = new class() implements RootZoneDatabaseCache {
            public function fetchByUri(string $uri): ?RootZoneDatabase
            {
                return TopLevelDomains::fromPath(dirname(__DIR__).'/data/tlds-alpha-by-domain.txt');
            }

            public function storeByUri(string $uri, RootZoneDatabase $topLevelDomains): bool
            {
                return true;
            }
        };

        $client = new class() implements RootZoneDatabaseStorage {
            public function getByUri(string $uri): RootZoneDatabase
            {
                return TopLevelDomains::fromPath(dirname(__DIR__).'/data/tlds-alpha-by-domain.txt');
            }
        };

        $storage = new RootZoneDatabaseLocalStorage($cache, $client);

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

        $client = new class() implements RootZoneDatabaseStorage {
            public function getByUri(string $uri): RootZoneDatabase
            {
                return TopLevelDomains::fromPath(dirname(__DIR__).'/data/tlds-alpha-by-domain.txt');
            }
        };

        $storage = new RootZoneDatabaseLocalStorage($cache, $client);

        self::assertInstanceOf(TopLevelDomains::class, $storage->getByUri('http://www.example.com'));
    }
}
