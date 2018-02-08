<?php

declare(strict_types=1);

namespace pdp\tests;

use Pdp\Domain;
use Pdp\Exception;
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

    public function testToAsciiThrowsException()
    {
        $this->expectException(Exception::class);
        (new Domain('_b%C3%A9bé.be-'))->toAscii();
    }

    public function testToUnicodeThrowsException()
    {
        $this->expectException(Exception::class);
        (new Domain('xn--a-ecp.ru'))->toUnicode();
    }

    public function invalidRegistrableDomainProvider()
    {
        return [
            'domain and suffix are the same' => ['co.uk', 'co.uk'],
            'domain has no labels' => ['faketld', 'faketld'],
            'public suffix is null' => ['faketld', null],
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

    /**
     * @dataProvider toUnicodeProvider
     * @param null|string $domain
     * @param null|string $publicSuffix
     * @param null|string $expectedDomain
     * @param null|string $expectedSuffix
     * @param null|string $expectedIDNDomain
     * @param null|string $expectedIDNSuffix
     */
    public function testToIDN(
        $domain,
        $publicSuffix,
        $expectedDomain,
        $expectedSuffix,
        $expectedIDNDomain,
        $expectedIDNSuffix
    ) {
        $domain = new Domain($domain, new PublicSuffix($publicSuffix));
        $this->assertSame($expectedDomain, $domain->getDomain());
        $this->assertSame($expectedSuffix, $domain->getPublicSuffix());

        $domainIDN = $domain->toUnicode();
        $this->assertSame($expectedIDNDomain, $domainIDN->getDomain());
        $this->assertSame($expectedIDNSuffix, $domainIDN->getPublicSuffix());
    }

    public function toUnicodeProvider()
    {
        return [
            'simple domain' => [
                'domain' => 'www.ulb.ac.be',
                'publicSuffix' => 'ac.be',
                'expectedDomain' => 'www.ulb.ac.be',
                'expectedSuffix' => 'ac.be',
                'expectedIDNDomain' => 'www.ulb.ac.be',
                'expectedIDNSuffix' => 'ac.be',
            ],
            'ASCII to IDN domain' => [
                'domain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'publicSuffix' => 'xn--85x722f.xn--55qx5d.cn',
                'expectedDomain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'expectedSuffix' => 'xn--85x722f.xn--55qx5d.cn',
                'expectedIDNDomain' => 'www.食狮.公司.cn',
                'expectedIDNSuffix' => '食狮.公司.cn',
            ],
            'IDN to IDN domain' => [
                'domain' => 'www.食狮.公司.cn',
                'publicSuffix' => '食狮.公司.cn',
                'expectedDomain' => 'www.食狮.公司.cn',
                'expectedSuffix' => '食狮.公司.cn',
                'expectedIDNDomain' => 'www.食狮.公司.cn',
                'expectedIDNSuffix' => '食狮.公司.cn',
            ],
            'null domain and suffix' => [
                'domain' => null,
                'publicSuffix' => null,
                'expectedDomain' => null,
                'expectedSuffix' => null,
                'expectedIDNDomain' => null,
                'expectedIDNSuffix' => null,
            ],
            'domain with null suffix' => [
                'domain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'publicSuffix' => null,
                'expectedDomain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'expectedSuffix' => null,
                'expectedIDNDomain' => 'www.食狮.公司.cn',
                'expectedIDNSuffix' => null,
            ],
        ];
    }

    /**
     * @dataProvider toAsciiProvider
     * @param null|string $domain
     * @param null|string $publicSuffix
     * @param null|string $expectedDomain
     * @param null|string $expectedSuffix
     * @param null|string $expectedAsciiDomain
     * @param null|string $expectedAsciiSuffix
     */
    public function testToAscii(
        $domain,
        $publicSuffix,
        $expectedDomain,
        $expectedSuffix,
        $expectedAsciiDomain,
        $expectedAsciiSuffix
    ) {
        $domain = new Domain($domain, new PublicSuffix($publicSuffix));
        $this->assertSame($expectedDomain, $domain->getDomain());
        $this->assertSame($expectedSuffix, $domain->getPublicSuffix());

        $domainIDN = $domain->toAscii();
        $this->assertSame($expectedAsciiDomain, $domainIDN->getDomain());
        $this->assertSame($expectedAsciiSuffix, $domainIDN->getPublicSuffix());
    }

    public function toAsciiProvider()
    {
        return [
            'simple domain' => [
                'domain' => 'www.ulb.ac.be',
                'publicSuffix' => 'ac.be',
                'expectedDomain' => 'www.ulb.ac.be',
                'expectedSuffix' => 'ac.be',
                'expectedIDNDomain' => 'www.ulb.ac.be',
                'expectedIDNSuffix' => 'ac.be',
            ],
            'ASCII to ASCII domain' => [
                'domain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'publicSuffix' => 'xn--85x722f.xn--55qx5d.cn',
                'expectedDomain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'expectedSuffix' => 'xn--85x722f.xn--55qx5d.cn',
                'expectedIDNDomain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'expectedIDNSuffix' => 'xn--85x722f.xn--55qx5d.cn',
            ],
            'ASCII to IDN domain' => [
                'domain' => 'www.食狮.公司.cn',
                'publicSuffix' => '食狮.公司.cn',
                'expectedDomain' => 'www.食狮.公司.cn',
                'expectedSuffix' => '食狮.公司.cn',
                'expectedIDNDomain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'expectedIDNSuffix' => 'xn--85x722f.xn--55qx5d.cn',
            ],
            'null domain and suffix' => [
                'domain' => null,
                'publicSuffix' => null,
                'expectedDomain' => null,
                'expectedSuffix' => null,
                'expectedIDNDomain' => null,
                'expectedIDNSuffix' => null,
            ],
            'domain with null suffix' => [
                'domain' => 'www.食狮.公司.cn',
                'publicSuffix' => null,
                'expectedDomain' => 'www.食狮.公司.cn',
                'expectedSuffix' => null,
                'expectedIDNDomain' => 'www.xn--85x722f.xn--55qx5d.cn',
                'expectedIDNSuffix' => null,
            ],
        ];
    }
}
