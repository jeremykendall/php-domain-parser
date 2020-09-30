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
use Psr\Log\LoggerInterface;
use Throwable;

final class TopLevelDomainsCache implements RootZoneDatabaseCache
{
    private JsonSerializableCache $cache;

    private ?LoggerInterface $logger;

    public function __construct(JsonSerializableCache $cache, LoggerInterface $logger = null)
    {
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function fetch(string $uri): ?RootZoneDatabase
    {
        $cacheData = $this->cache->fetch($uri);
        if (null === $cacheData) {
            return null;
        }

        try {
            $topLevelDomains = TopLevelDomains::fromJsonString($cacheData);
        } catch (Throwable $exception) {
            $this->cache->forget($uri);
            if (null !== $this->logger) {
                $this->logger->error($exception->getMessage());
            }

            return null;
        }

        return $topLevelDomains;
    }

    public function store(string $uri, RootZoneDatabase $topLevelDomains): bool
    {
        return $this->cache->store($uri, $topLevelDomains);
    }

    public function delete(string $uri): bool
    {
        return $this->cache->forget($uri);
    }
}
