<?php

declare(strict_types=1);

namespace pdp\tests;

use Pdp\Domain;
use Pdp\PublicSuffix;
use Pdp\Rules;
use PHPUnit\Framework\TestCase;

class DomainTest extends TestCase
{
    /**
     * @dataProvider invalidRegistrableDomainProvider
     *
     * @param string $domain
     * @param string $publicSuffix
     */
    public function testRegistrableDomainIsNullWithFoundDomain(string $domain, $publicSuffix)
    {
        $domain = new Domain($domain, new PublicSuffix($publicSuffix));
        $this->assertNull($domain->getRegistrableDomain());
        $this->assertNull($domain->getSubDomain());
    }

    public function invalidRegistrableDomainProvider()
    {
        return [
            'domain and suffix are the same' => ['co.uk', 'co.uk'],
            'domain has no labels' => ['faketld', 'faketld'],
            'public suffix is null' => ['faketld', null],
            'public suffix is invalid' => ['_b%C3%A9bé.be-', 'be-'],
        ];
    }

    public function testDomainInternalPhpMethod()
    {
        $domain = new Domain('www.ulb.ac.be', new PublicSuffix('ac.be', Rules::ICANN_DOMAINS));
        $generateDomain = eval('return '.var_export($domain, true).';');
        $this->assertInternalType('array', $domain->__debugInfo());
        $this->assertEquals($domain, $generateDomain);
    }

    public function testPublicSuffixnternalPhpMethod()
    {
        $publicSuffix = new PublicSuffix('co.uk', Rules::ICANN_DOMAINS);
        $generatePublicSuffix = eval('return '.var_export($publicSuffix, true).';');
        $this->assertInternalType('array', $publicSuffix->__debugInfo());
        $this->assertEquals($publicSuffix, $generatePublicSuffix);
    }
}
