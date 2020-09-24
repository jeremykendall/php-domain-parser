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
use Throwable;

final class RulesPsr16Cache implements PublicSuffixListCache
{
    private JsonSerializablePsr16Cache $cache;

    public function __construct(JsonSerializablePsr16Cache $cache)
    {
        $this->cache = $cache;
    }

    public function fetchByUri(string $uri): ?Rules
    {
        $cacheData = $this->cache->fetch($uri);
        if (null === $cacheData) {
            return null;
        }

        try {
            $rules = Rules::fromJsonString($cacheData);
        } catch (Throwable $exception) {
            $this->cache->forget($uri, $exception);

            return null;
        }

        return $rules;
    }

    public function storeByUri(string $uri, PublicSuffixList $publicSuffixList): bool
    {
        return $this->cache->store($uri, $publicSuffixList);
    }
}