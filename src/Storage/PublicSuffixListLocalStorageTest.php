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

use Pdp\PublicSuffixList;
use Pdp\Rules;
use PHPUnit\Framework\TestCase;
use function dirname;

final class PublicSuffixListLocalStorageTest extends TestCase
{
    public function testIsCanReturnAPublicSuffixListInstanceFromCache(): void
    {
        $cache = new class() implements PublicSuffixListCache {
            public function fetchByUri(string $uri): ?PublicSuffixList
            {
                return Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
            }

            public function storeByUri(string $uri, PublicSuffixList $publicSuffixList): bool
            {
                return true;
            }
        };

        $client = new class() implements PublicSuffixListStorage {
            public function getByUri(string $uri): PublicSuffixList
            {
                return Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
            }
        };

        $storage = new PublicSuffixListLocalStorage($cache, $client);
        $psl = $storage->getByUri('http://www.example.com');

        self::assertInstanceOf(Rules::class, $psl);
    }

    public function testIsCanReturnAPublicSuffixListInstanceFromTheInnerStorage(): void
    {
        $cache = new class() implements PublicSuffixListCache {
            public function fetchByUri(string $uri): ?PublicSuffixList
            {
                return null;
            }

            public function storeByUri(string $uri, PublicSuffixList $publicSuffixList): bool
            {
                return true;
            }
        };

        $client = new class() implements PublicSuffixListStorage {
            public function getByUri(string $uri): PublicSuffixList
            {
                return Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
            }
        };

        $storage = new PublicSuffixListLocalStorage($cache, $client);
        $psl = $storage->getByUri('http://www.example.com');

        self::assertInstanceOf(Rules::class, $psl);
    }
}
