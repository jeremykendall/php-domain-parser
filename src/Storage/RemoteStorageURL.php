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

interface RemoteStorageURL
{
    public const PSL_URL = 'https://publicsuffix.org/list/public_suffix_list.dat';

    public const RZD_URL = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';
}
