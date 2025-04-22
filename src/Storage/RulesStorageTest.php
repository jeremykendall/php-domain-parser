<?php

declare(strict_types=1);

namespace Pdp\Storage;

use Pdp\PublicSuffixList;
use Pdp\Rules;
use PHPUnit\Framework\TestCase;

use function dirname;

final class RulesStorageTest extends TestCase
{
    public function testIsCanReturnAPublicSuffixListInstanceFromCache(): void
    {
        $cache = new class () implements PublicSuffixListCache {
            public function fetch(string $uri): ?PublicSuffixList
            {
                return Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
            }

            public function remember(string $uri, PublicSuffixList $publicSuffixList): bool
            {
                return true;
            }

            public function forget(string $uri): bool
            {
                return true;
            }
        };

        $client = new class () implements PublicSuffixListClient {
            public function get(string $uri): PublicSuffixList
            {
                return Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
            }
        };

        $storage = new RulesStorage($cache, $client);
        $psl = $storage->get('http://www.example.com');

        self::assertInstanceOf(Rules::class, $psl);
    }

    public function testIsCanReturnAPublicSuffixListInstanceFromTheInnerStorage(): void
    {
        $cache = new class () implements PublicSuffixListCache {
            public function fetch(string $uri): ?PublicSuffixList
            {
                return null;
            }

            public function remember(string $uri, PublicSuffixList $publicSuffixList): bool
            {
                return true;
            }

            public function forget(string $uri): bool
            {
                return true;
            }
        };

        $client = new class () implements PublicSuffixListClient {
            public function get(string $uri): PublicSuffixList
            {
                return Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
            }
        };

        $storage = new RulesStorage($cache, $client);
        $psl = $storage->get('http://www.example.com');

        self::assertInstanceOf(Rules::class, $psl);
    }

    public function testIsCanDeleteAPublicSuffixListInstanceFromTheInnerStorage(): void
    {
        $cache = new class () implements PublicSuffixListCache {
            public function fetch(string $uri): ?PublicSuffixList
            {
                return null;
            }

            public function remember(string $uri, PublicSuffixList $publicSuffixList): bool
            {
                return true;
            }

            public function forget(string $uri): bool
            {
                return 'http://www.example.com' === $uri;
            }
        };

        $client = new class () implements PublicSuffixListClient {
            public function get(string $uri): PublicSuffixList
            {
                return Rules::fromPath(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
            }
        };

        $storage = new RulesStorage($cache, $client);
        $storage->get('http://www.example.com');

        self::assertTrue($storage->delete('http://www.example.com'));
        self::assertFalse($storage->delete('https://www.example.com'));
    }
}
