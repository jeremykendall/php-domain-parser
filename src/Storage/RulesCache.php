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

use Pdp\PublicSuffixList;
use Pdp\Rules;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final class RulesCache implements PublicSuffixListCache
{
    private JsonSerializableCache $cache;

    private LoggerInterface $logger;

    public function __construct(JsonSerializableCache $cache, LoggerInterface $logger = null)
    {
        $this->cache = $cache;
        $this->logger = $logger ?? new NullLogger();
    }

    public function fetch(string $uri): ?PublicSuffixList
    {
        $cacheData = $this->cache->fetch($uri);
        if (null === $cacheData) {
            return null;
        }

        try {
            $rules = Rules::fromJsonString($cacheData);
        } catch (Throwable $exception) {
            $this->cache->forget($uri);
            $this->logger->error($exception->getMessage());

            return null;
        }

        return $rules;
    }

    public function store(string $uri, PublicSuffixList $publicSuffixList): bool
    {
        return $this->cache->store($uri, $publicSuffixList);
    }

    public function delete(string $uri): bool
    {
        return $this->cache->forget($uri);
    }
}
