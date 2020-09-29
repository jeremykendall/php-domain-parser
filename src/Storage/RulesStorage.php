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

final class RulesStorage implements PublicSuffixListStorage
{
    private PublicSuffixListClient $client;

    private PublicSuffixListCache $cache;

    public function __construct(PublicSuffixListClient $client, PublicSuffixListCache $cache)
    {
        $this->client = $client;
        $this->cache = $cache;
    }

    public function get(string $uri): PublicSuffixList
    {
        $publicSuffixList = $this->cache->fetch($uri);
        if (null !== $publicSuffixList) {
            return $publicSuffixList;
        }

        $publicSuffixList = $this->client->get($uri);

        $this->cache->store($uri, $publicSuffixList);

        return $publicSuffixList;
    }

    public function delete(string $uri): bool
    {
        return $this->cache->delete($uri);
    }
}
