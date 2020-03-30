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

/**
 * Constants used to name Public Suffix list section.
 *
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
interface PublicSuffixListSection
{
    public const ICANN_DOMAINS = 'ICANN_DOMAINS';

    public const PRIVATE_DOMAINS = 'PRIVATE_DOMAINS';
}
