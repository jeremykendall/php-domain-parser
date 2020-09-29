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

interface DomainResolver
{
    /**
     * Returns PSL info for a given domain.
     *
     * If the effective TLD can not be resolved it returns a ResolvedDomainName with a null public suffix
     * If the host is not a valid domain it returns a ResolvedDomainName with a null Domain name
     */
    public function resolve(Host $host): ResolvedDomainName;
}
