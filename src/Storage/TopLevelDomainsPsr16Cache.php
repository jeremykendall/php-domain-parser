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
use Throwable;

final class TopLevelDomainsPsr16Cache implements RootZoneDatabaseCache
{
    private JsonSerializablePsr16Cache $cache;

    public function __construct(JsonSerializablePsr16Cache $cache)
    {
        $this->cache = $cache;
    }

    public function fetchByUri(string $uri): ?RootZoneDatabase
    {
        $cacheData = $this->cache->fetch($uri);
        if (null === $cacheData) {
            return null;
        }

        try {
            $topLevelDomains = TopLevelDomains::fromJsonString($cacheData);
        } catch (Throwable $exception) {
            $this->cache->forget($uri, $exception);

            return null;
        }

        return $topLevelDomains;
    }

    public function storeByUri(string $uri, RootZoneDatabase $topLevelDomains): bool
    {
        return $this->cache->store($uri, $topLevelDomains);
    }
}