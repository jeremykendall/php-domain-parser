<?php
/**
 * Public Suffix List PHP: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/publicsuffixlist-php for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 * @license   http://github.com/jeremykendall/publicsuffixlist-php/blob/master/LICENSE MIT License
 */
declare(strict_types=1);

namespace Pdp;

interface Domain
{
    /**
     * @return string|null
     */
    public function getDomain();

    /**
     * @return string|null
     */
    public function getPublicSuffix();

    /**
     * Get registrable domain.
     *
     * Algorithm #7: The registered or registrable domain is the public suffix
     * plus one additional label.
     *
     * This method should return null if the domain provided is a public suffix,
     * per the test cases provided by Mozilla.
     *
     * @see https://publicsuffix.org/list/
     * @see https://raw.githubusercontent.com/publicsuffix/list/master/tests/test_psl.txt
     *
     * @return string|null registrable domain
     */
    public function getRegistrableDomain();

    /**
     * Does the domain have a matching rule in the Public Suffix List?
     *
     * WARNING: "Some people use the PSL to determine what is a valid domain name
     * and what isn't. This is dangerous, particularly in these days where new
     * gTLDs are arriving at a rapid pace, if your software does not regularly
     * receive PSL updates, because it will erroneously think new gTLDs are not
     * valid. The DNS is the proper source for this information. If you must use
     * it for this purpose, please do not bake static copies of the PSL into your
     * software with no update mechanism."
     *
     * @see https://publicsuffix.org/learn/
     *
     * @return bool
     */
    public function isValid(): bool;
}