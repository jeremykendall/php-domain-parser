<?php

declare(strict_types=1);

/**
 * Public Suffix List PHP: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/publicsuffixlist-php for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 * @license   http://github.com/jeremykendall/publicsuffixlist-php/blob/master/LICENSE MIT License
 */
namespace Pdp;

use Pdp\MatchedDomain;
use Pdp\UnmatchedDomain;
use PHPUnit\Framework\TestCase;

class DomainTest extends TestCase
{
    /**
     * @dataProvider invalidRegistrableDomainProvider
     *
     * @param string      $domain
     * @param string|null $publicSuffix
     */
    public function testRegistrableDomainIsNullWithMatchedDomain(string $domain, $publicSuffix)
    {
        $domain = new MatchedDomain($domain, $publicSuffix);
        $this->assertNull($domain->getRegistrableDomain());
    }

    public function invalidRegistrableDomainProvider()
    {
        return [
            'domain and suffix are the same' => ['co.uk', 'co.uk'],
            'publicSuffix is null' => ['example.faketld', null],
            'domain has no labels' => ['faketld', 'faketld'],
        ];
    }

    /**
     * @dataProvider invalidRegistrableDomainProvider
     *
     * @param string      $domain
     * @param string|null $publicSuffix
     */
    public function testRegistrableDomainIsNullWithUnMatchedDomain(string $domain, $publicSuffix)
    {
        $domain = new UnmatchedDomain($domain, $publicSuffix);
        $this->assertNull($domain->getRegistrableDomain());
    }
}
