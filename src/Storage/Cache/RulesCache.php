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

namespace Pdp\Storage\Cache;

use Pdp\Rules;

/**
 * Public Suffix List Manager.
 *
 * This class obtains, writes, caches, and returns PHP representations
 * of the Public Suffix List ICANN section
 *
 * @author Jeremy Kendall <jeremy@jeremykendall.net>
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
interface RulesCache
{
    /**
     * Gets the Public Suffix List Rules.
     */
    public function fetchByUri(string $uri): ?Rules;

    /**
     * Cache the Public Suffix List Rules.
     *
     * If a local cache already exists, it will be overwritten.
     *
     * Returns true if the refresh was successful, false otherwise
     */
    public function storeByUri(string $uri, Rules $rules): bool;
}
