<?php

declare(strict_types=1);

namespace Pdp\Tests;

use Pdp\Domain;
use Pdp\Exception;
use Pdp\PublicSuffix;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Pdp\Domain
 */
class DomainTest extends TestCase
{
    /**
     * @dataProvider invalidRegistrableDomainProvider
     *
     * @param string|null $domain
     * @param string|null $publicSuffix
     *
     * @covers ::__construct
     * @covers ::setPublicSuffix
     * @covers ::setRegistrableDomain
     * @covers ::setSubDomain
     * @covers ::assertValidState
     * @covers ::getPublicSuffix
     * @covers ::getRegistrableDomain
     * @covers ::getSubDomain
     */
    public function testRegistrableDomainIsNullWithFoundDomain($domain, $publicSuffix)
    {
        $domain = new Domain($domain, new PublicSuffix($publicSuffix));
        $this->assertNull($domain->getPublicSuffix());
        $this->assertNull($domain->getRegistrableDomain());
        $this->assertNull($domain->getSubDomain());
    }

    public function invalidRegistrableDomainProvider()
    {
        return [
            'domain and suffix are the same' => ['co.uk', 'co.uk'],
            'domain has no labels' => ['faketld', 'faketld'],
            'public suffix is null' => ['faketld', null],
            'domain is null' => [null, 'faketld'],
        ];
    }

    /**
     * @covers ::__construct
     * @covers ::assertValidState
     */
    public function testConstructorThrowsExceptionOnMisMatchPublicSuffixDomain()
    {
        $this->expectException(Exception::class);
        new Domain('www.ulb.ac.be', new PublicSuffix('com'));
    }

    /**
     * @covers ::__construct
     * @covers ::setDomain
     * @covers ::getIdnErrors
     */
    public function testToAsciiThrowsException()
    {
        $this->expectException(Exception::class);
        new Domain('a⒈com');
    }

    /**
     * @covers ::toUnicode
     * @covers ::idnToUnicode
     * @covers ::getIdnErrors
     */
    public function testToUnicodeThrowsException()
    {
        $this->expectException(Exception::class);
        (new Domain('xn--a-ecp.ru'))->toUnicode();
    }

    /**
     * @covers ::__construct
     * @covers ::__set_state
     * @covers ::__debugInfo
     * @covers ::jsonSerialize
     * @covers ::getIterator
     */
    public function testDomainInternalPhpMethod()
    {
        $domain = new Domain('www.ulb.ac.be', new PublicSuffix('ac.be'));
        $generateDomain = eval('return '.var_export($domain, true).';');
        $this->assertInternalType('array', $domain->__debugInfo());
        $this->assertEquals($domain, $generateDomain);
        $this->assertSame(['be', 'ac', 'ulb', 'www'], iterator_to_array($domain));
        $this->assertJsonStringEqualsJsonString(
            json_encode($domain->__debugInfo()),
            json_encode($domain)
        );
    }

    /**
     * @dataProvider countableProvider
     * @param string|null $domain
     * @param int         $nbLabels
     * @param string[]    $labels
     * @covers ::getIterator
     * @covers ::count
     */
    public function testCountable($domain, $nbLabels, $labels)
    {
        $domain = new Domain($domain);
        $this->assertCount($nbLabels, $domain);
        $this->assertSame($labels, iterator_to_array($domain));
    }

    public function countableProvider()
    {
        return [
            'null' => [null, 0, []],
            'empty string' => ['', 1, ['']],
            'simple' => ['foo.bar.baz', 3, ['baz', 'bar', 'foo']],
            'unicode' => ['www.食狮.公司.cn', 4, ['cn', '公司', '食狮', 'www']],
        ];
    }

    /**
     * @covers ::getLabel
     */
    public function testGetLabel()
    {
        $domain = new Domain('master.example.com');
        $this->assertSame('com', $domain->getLabel(0));
        $this->assertSame('example', $domain->getLabel(1));
        $this->assertSame('master', $domain->getLabel(-1));
        $this->assertNull($domain->getLabel(23));
        $this->assertNull($domain->getLabel(-23));
    }

    /**
     * @covers ::keys
     */
    public function testOffsets()
    {
        $domain = new Domain('master.com.example.com');
        $this->assertSame([0, 2], $domain->keys('com'));
        $this->assertSame([], $domain->keys('toto'));
    }

    /**
     * @dataProvider toUnicodeProvider
     * @param null|string $domain
     * @param null|string $publicSuffix
     * @param null|string $expectedDomain
     * @param null|string $expectedSuffix
     * @param null|string $expectedIDNDomain
     * @param null|string $expectedIDNSuffix
     *
     * @covers ::setDomain
     * @covers ::setPublicSuffix
     * @covers ::assertValidState
     * @covers ::setRegistrableDomain
     * @covers ::setSubDomain
     * @covers ::getDomain
     * @covers ::getContent
     * @covers ::getPublicSuffix
     * @covers ::idnToUnicode
     * @covers ::toUnicode
     * @covers \Pdp\PublicSuffix::toUnicode
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
            'empty string domain and null suffix' => [
                'domain' => '',
                'publicSuffix' => null,
                'expectedDomain' => '',
                'expectedSuffix' => null,
                'expectedIDNDomain' => '',
                'expectedIDNSuffix' => null,
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
            'domain with URLencoded data' => [
                'domain' => 'b%C3%A9b%C3%A9.be',
                'publicSuffix' => 'be',
                'expectedDomain' => 'bébé.be',
                'expectedSuffix' => 'be',
                'expectedIDNDomain' => 'bébé.be',
                'expectedIDNSuffix' => 'be',
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
     *
     * @covers ::setDomain
     * @covers ::setPublicSuffix
     * @covers ::assertValidState
     * @covers ::setRegistrableDomain
     * @covers ::setSubDomain
     * @covers ::getDomain
     * @covers ::getContent
     * @covers ::getPublicSuffix
     * @covers ::idnToAscii
     * @covers ::toAscii
     * @covers \Pdp\PublicSuffix::toAscii
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
