<?php
/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 * @license   http://github.com/jeremykendall/php-domain-parser/blob/master/LICENSE MIT License
 */
declare(strict_types=1);

namespace Pdp;

/**
 * Constants used to name Public Suffix list section.
 *
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
interface SectionInterface
{
    const ALL_DOMAINS = 'ALL_DOMAINS';
    const ICANN_DOMAINS = 'ICANN_DOMAINS';
    const PRIVATE_DOMAINS = 'PRIVATE_DOMAINS';
}
