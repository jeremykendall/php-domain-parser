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

namespace Pdp;

use Pdp\Exception\CouldNotLoadRules;

/**
 * Manager Interface.
 */
interface ManagerInterface
{
    const PSL_URL = 'https://publicsuffix.org/list/public_suffix_list.dat';

    /**
     * Gets the Public Suffix List Rules.
     *
     * @param string $source_url the Public Suffix List URL
     *
     * @throws CouldNotLoadRules If the PSL rules can not be loaded
     *
     * @return Rules
     */
    public function getRules(string $source_url = self::PSL_URL);

    /**
     * Downloads, converts and cache the Public Suffix.
     *
     * If a local cache already exists, it will be overwritten.
     *
     * Returns true if the refresh was successful
     *
     * @param string $source_url the Public Suffix List URL
     *
     * @return bool
     */
    public function refreshRules(string $source_url = self::PSL_URL);
}
