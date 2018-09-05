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

namespace Pdp\Tests;

use Pdp\Domain;
use Pdp\Exception\CouldNotResolvePublicSuffix;
use Pdp\Exception\CouldNotResolveSubDomain;
use Pdp\Exception\InvalidDomain;
use Pdp\Exception\InvalidLabel;
use Pdp\Exception\InvalidLabelKey;
use Pdp\PublicSuffix;
use Pdp\Rules;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @coversDefaultClass Pdp\Domain
 */
class DomainTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::setPublicSuffix
     * @covers ::setRegistrableDomain
     * @covers ::setSubDomain
     * @covers ::getPublicSuffix
     * @covers ::getRegistrableDomain
     * @covers ::getSubDomain
     */
    public function testRegistrableDomainIsNullWithFoundDomain()
    {
        $domain = new Domain('faketld', null);
        $this->assertNull($domain->getPublicSuffix());
        $this->assertNull($domain->getRegistrableDomain());
        $this->assertNull($domain->getSubDomain());
    }

    /**
     * @covers ::__construct
     * @covers ::setPublicSuffix
     * @covers ::normalize
     * @dataProvider provideWrongConstructor
     *
     * @param mixed $domain
     * @param mixed $publicSuffix
     */
    public function testConstructorThrowsExceptionOnMisMatchPublicSuffixDomain($domain, $publicSuffix)
    {
        $this->expectException(CouldNotResolvePublicSuffix::class);
        new Domain($domain, new PublicSuffix($publicSuffix));
    }

    public function provideWrongConstructor()
    {
        return [
            'public suffix mismatch' => [
                'domain' => 'www.ulb.ac.be',
                'publicSuffix' => 'com',
            ],
            'domain and public suffix are the same' => [
                'domain' => 'co.uk',
                'publicSuffix' => 'co.uk',
            ],
            'domain has no labels' => [
                'domain' => 'localhost',
                'publicSuffix' => 'localhost',
            ],
            'domain is null' => [
                'domain' => null,
                'publicSuffix' => 'com',
            ],
        ];
    }

    /**
     * @dataProvider invalidDomainProvider
     * @covers ::__construct
     * @covers ::setLabels
     * @covers ::idnToAscii
     * @covers ::getIdnErrors
     * @param string $domain
     */
    public function testToAsciiThrowsException(string $domain)
    {
        $this->expectException(InvalidDomain::class);
        new Domain($domain);
    }

    public function invalidDomainProvider()
    {
        return [
            'invalid IDN domain' => ['a⒈com'],
            'invalid IDN domain full size' => ['％００.com'],
            'invalid IDN domain full size rawurlencode ' => ['%ef%bc%85%ef%bc%94%ef%bc%91.com'],
        ];
    }

    /**
     * @covers ::toUnicode
     * @covers ::idnToUnicode
     * @covers ::getIdnErrors
     */
    public function testToUnicodeThrowsException()
    {
        $this->expectException(InvalidDomain::class);
        (new Domain('xn--a-ecp.ru'))->toUnicode();
    }

    /**
     * @covers ::__construct
     * @covers ::__set_state
     * @covers ::__debugInfo
     * @covers ::__toString
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
        $this->assertSame('www.ulb.ac.be', (string) $domain);
    }

    /**
     * @covers ::normalize
     * @covers ::getIterator
     * @covers ::count
     * @dataProvider countableProvider
     *
     * @param string|null $domain
     * @param int         $nbLabels
     * @param string[]    $labels
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
     * @covers ::setLabels
     * @covers ::setPublicSuffix
     * @covers ::normalize
     * @covers ::setRegistrableDomain
     * @covers ::setSubDomain
     * @covers ::getDomain
     * @covers ::getContent
     * @covers ::getPublicSuffix
     * @covers ::idnToUnicode
     * @covers ::toUnicode
     * @covers \Pdp\PublicSuffix::toUnicode
     * @dataProvider toUnicodeProvider
     *
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
     * @covers ::setLabels
     * @covers ::setPublicSuffix
     * @covers ::normalize
     * @covers ::setRegistrableDomain
     * @covers ::setSubDomain
     * @covers ::getDomain
     * @covers ::getContent
     * @covers ::getPublicSuffix
     * @covers ::idnToAscii
     * @covers ::toAscii
     * @covers \Pdp\PublicSuffix::toAscii
     *
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

    /**
     * @covers ::resolve
     * @covers ::normalize
     * @dataProvider resolvePassProvider
     *
     * @param string|null $expected
     * @param Domain      $domain
     * @param mixed       $publicSuffix
     */
    public function testResolveWorks(Domain $domain, $publicSuffix, $expected)
    {
        $this->assertSame($expected, $domain->resolve($publicSuffix)->getPublicSuffix());
    }

    public function resolvePassProvider()
    {
        $publicSuffix = new PublicSuffix('ac.be', Rules::ICANN_DOMAINS);
        $domain = new Domain('ulb.ac.be', $publicSuffix);

        return [
            'null public suffix' => [
                'domain' => $domain,
                'public suffix' => new PublicSuffix(),
                'expected' => null,
            ],
            'null public suffix (with null value)' => [
                'domain' => $domain,
                'public suffix' => null,
                'expected' => null,
            ],
            'same public suffix' => [
                'domain' => $domain,
                'public suffix' => $publicSuffix,
                'expected' => 'ac.be',
            ],
            'same public suffix (with string value)' => [
                'domain' => $domain,
                'public suffix' => 'ac.be',
                'expected' => 'ac.be',
            ],
            'update public suffix' => [
                'domain' => $domain,
                'public suffix' => new PublicSuffix('be', Rules::ICANN_DOMAINS),
                'expected' => 'be',
            ],
            'idn domain name' => [
                'domain' =>  new Domain('Яндекс.РФ', new PublicSuffix('рф', Rules::ICANN_DOMAINS)),
                'public suffix' => new PublicSuffix('рф', Rules::ICANN_DOMAINS),
                'expected' => 'рф',
            ],
            'idn domain name with ascii public suffix' => [
                'domain' =>  new Domain('Яндекс.РФ', new PublicSuffix('рф', Rules::ICANN_DOMAINS)),
                'public suffix' => new PublicSuffix('xn--p1ai', Rules::ICANN_DOMAINS),
                'expected' => 'рф',
            ],
        ];
    }

    /**
     * @covers ::resolve
     * @dataProvider resolveFailsProvider
     *
     * @param Domain       $domain
     * @param PublicSuffix $publicSuffix
     */
    public function testResolveFails(Domain $domain, PublicSuffix $publicSuffix)
    {
        $this->expectException(CouldNotResolvePublicSuffix::class);
        $domain->resolve($publicSuffix);
    }

    public function resolveFailsProvider()
    {
        $publicSuffix = new PublicSuffix('ac.be', Rules::ICANN_DOMAINS);
        $domain = new Domain('ulb.ac.be', $publicSuffix);

        return [
            'public suffix mismatch' => [
                'domain' => $domain,
                'public suffix' => new PublicSuffix('ac.fr'),
            ],
            'domain name can not contains public suffix' => [
                'domain' => new Domain('localhost'),
                'public suffix' => $publicSuffix,
            ],
            'domain name is equal to public suffix' => [
                'domain' => new Domain('ac.be'),
                'public suffix' => $publicSuffix,
            ],
            'partial public suffix' => [
                'domain' => $domain,
                'public suffix' => new PublicSuffix('c.be'),
            ],
            'mismatch idn public suffix' => [
                'domain' => new Domain('www.食狮.公司.cn'),
                'public suffix' => new PublicSuffix('cn.公司'),
            ],
        ];
    }

    /**
     * @covers ::resolve
     */
    public function testResolveReturnsInstance()
    {
        $publicSuffix = new PublicSuffix('ac.be', Rules::ICANN_DOMAINS);
        $domain = new Domain('ulb.ac.be', $publicSuffix);
        $this->assertSame($domain, $domain->resolve($publicSuffix));
        $this->assertNotSame($domain, $domain->resolve(new PublicSuffix('ac.be', Rules::PRIVATE_DOMAINS)));
    }

    /**
     * @covers ::withSubDomain
     * @covers ::normalizeContent
     * @dataProvider withSubDomainWorksProvider
     *
     * @param null|string $expected
     * @param Domain      $domain
     * @param mixed       $subdomain
     */
    public function testWithSubDomainWorks(Domain $domain, $subdomain, $expected)
    {
        $result = $domain->withSubDomain($subdomain);
        $this->assertSame($expected, $result->getSubDomain());
        $this->assertSame($domain->getPublicSuffix(), $result->getPublicSuffix());
        $this->assertSame($domain->getRegistrableDomain(), $result->getRegistrableDomain());
        $this->assertSame($domain->isKnown(), $result->isKnown());
        $this->assertSame($domain->isICANN(), $result->isICANN());
        $this->assertSame($domain->isPrivate(), $result->isPrivate());
    }

    public function withSubDomainWorksProvider()
    {
        return [
            'simple addition' => [
                'domain' => new Domain('example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS)),
                'subdomain' => 'www',
                'expected' => 'www',
            ],
            'simple addition IDN (1)' => [
                'domain' => new Domain('example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS)),
                'subdomain' => new Domain('bébé'),
                'expected' => 'xn--bb-bjab',
            ],
            'simple addition IDN (2)' => [
                'domain' => new Domain('Яндекс.РФ', new PublicSuffix('рф', Rules::ICANN_DOMAINS)),
                'subdomain' => 'bébé',
                'expected' => 'bébé',
            ],
            'simple removal' => [
                'domain' => new Domain('www.example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS)),
                'subdomain' => null,
                'expected' => null,
            ],
            'simple removal IDN' => [
                'domain' =>  new Domain('bébé.Яндекс.РФ', new PublicSuffix('рф', Rules::ICANN_DOMAINS)),
                'subdomain' => 'xn--bb-bjab',
                'expected' => 'bébé',
            ],
        ];
    }

    /**
     * @covers ::withSubDomain
     * @covers ::normalizeContent
     */
    public function testWithSubDomainFailsWithNullDomain()
    {
        $this->expectException(CouldNotResolveSubDomain::class);
        (new Domain())->withSubDomain('www');
    }

    /**
     * @covers ::withSubDomain
     * @covers ::normalizeContent
     */
    public function testWithSubDomainFailsWithOneLabelDomain()
    {
        $this->expectException(CouldNotResolveSubDomain::class);
        (new Domain('localhost'))->withSubDomain('www');
    }


    /**
     * @covers ::withSubDomain
     * @covers ::normalizeContent
     */
    public function testWithSubDomainFailsWithNonStringableObject()
    {
        $this->expectException(TypeError::class);
        (new Domain(
            'example.com',
            new PublicSuffix('com', PublicSuffix::ICANN_DOMAINS)
        ))->withSubDomain(date_create());
    }


    /**
     * @covers ::withSubDomain
     * @covers ::normalizeContent
     */
    public function testWithSubDomainWithoutPublicSuffixInfo()
    {
        $this->expectException(CouldNotResolveSubDomain::class);
        (new Domain('www.example.com'))->withSubDomain('www');
    }

    /**
     * @covers ::withPublicSuffix
     * @dataProvider withPublicSuffixWorksProvider
     *
     * @param null|string $expected
     * @param Domain      $domain
     * @param mixed       $publicSuffix
     * @param bool        $isKnown
     * @param bool        $isICANN
     * @param bool        $isPrivate
     */
    public function testWithPublicSuffixWorks(
        Domain $domain,
        $publicSuffix,
        $expected,
        bool $isKnown,
        bool $isICANN,
        bool $isPrivate
    ) {
        $result = $domain->withPublicSuffix($publicSuffix);
        $this->assertSame($expected, $result->getPublicSuffix());
        $this->assertSame($isKnown, $result->isKnown());
        $this->assertSame($isICANN, $result->isICANN());
        $this->assertSame($isPrivate, $result->isPrivate());
    }

    public function withPublicSuffixWorksProvider()
    {
        $base_domain = new Domain('example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS));

        return [
            'simple update (1)' => [
                'domain' => $base_domain,
                'publicSuffix' => 'be',
                'expected' => 'be',
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
            'simple update (2)' => [
                'domain' => $base_domain,
                'publicSuffix' => new PublicSuffix('github.io', Rules::PRIVATE_DOMAINS),
                'expected' => 'github.io',
                'isKnown' => true,
                'isICANN' => false,
                'isPrivate' => true,
            ],
            'same public suffix but PSL info is changed' => [
                'domain' => $base_domain,
                'publicSuffix' => new PublicSuffix('com', Rules::PRIVATE_DOMAINS),
                'expected' => 'com',
                'isKnown' => true,
                'isICANN' => false,
                'isPrivate' => true,
            ],
            'same public suffix but PSL info does not changed' => [
                'domain' => $base_domain,
                'publicSuffix' => new PublicSuffix('com', Rules::ICANN_DOMAINS),
                'expected' => 'com',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple update IDN (1)' => [
                'domain' => $base_domain,
                'publicSuffix' => new PublicSuffix('рф', Rules::ICANN_DOMAINS),
                'expected' => 'xn--p1ai',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple update IDN (2)' => [
                'domain' => new Domain('www.bébé.be', new PublicSuffix('be', Rules::ICANN_DOMAINS)),
                'publicSuffix' => new PublicSuffix('xn--p1ai', Rules::ICANN_DOMAINS),
                'expected' => 'рф',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'adding the public suffix to a single label domain' => [
                'domain' => new Domain('localhost'),
                'publicSuffix' => 'www',
                'expected' => 'www',
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
            'removing the public suffix list' => [
                'domain' => new Domain('www.bébé.be', new PublicSuffix('be', Rules::ICANN_DOMAINS)),
                'publicSuffix' => null,
                'expected' => null,
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
        ];
    }

    /**
     * @covers ::withPublicSuffix
     */
    public function testWithPublicSuffixFailsWithNullDomain()
    {
        $this->expectException(InvalidDomain::class);
        (new Domain())->withPublicSuffix('www');
    }

    /**
     * @covers ::withLabel
     * @covers ::normalizeContent
     * @dataProvider withLabelWorksProvider
     *
     * @param null|string $expected
     * @param Domain      $domain
     * @param int         $key
     * @param mixed       $label
     * @param bool        $isKnown
     * @param bool        $isICANN
     * @param bool        $isPrivate
     */
    public function testWithLabelWorks(
        Domain $domain,
        int $key,
        $label,
        $expected,
        bool $isKnown,
        bool $isICANN,
        bool $isPrivate
    ) {
        $result = $domain->withLabel($key, $label);
        $this->assertSame($expected, $result->getContent());
        $this->assertSame($isKnown, $result->isKnown());
        $this->assertSame($isICANN, $result->isICANN());
        $this->assertSame($isPrivate, $result->isPrivate());
    }

    public function withLabelWorksProvider()
    {
        $base_domain = new Domain('www.example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS));

        return [
            'null domain' => [
                'domain' => new Domain(),
                'key' => 0,
                'label' => 'localhost',
                'expected' => 'localhost',
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
            'simple replace positive offset' => [
                'domain' => $base_domain,
                'key' => 2,
                'label' => 'shop',
                'expected' => 'shop.example.com',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple replace negative offset' => [
                'domain' => $base_domain,
                'key' => -1,
                'label' => 'shop',
                'expected' => 'shop.example.com',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple addition positive offset' => [
                'domain' => $base_domain,
                'key' => 3,
                'label' => 'shop',
                'expected' => 'shop.www.example.com',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple addition negative offset' => [
                'domain' => $base_domain,
                'key' => -4,
                'label' => 'shop',
                'expected' => 'www.example.com.shop',
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
            'simple replace remove PSL info' => [
                'domain' => $base_domain,
                'key' => 0,
                'label' => 'fr',
                'expected' => 'www.example.fr',
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
            'replace without any change' => [
                'domain' => $base_domain,
                'key' => 2,
                'label' => 'www',
                'expected' => 'www.example.com',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple update IDN (1)' => [
                'domain' => $base_domain,
                'key' => 2,
                'label' => 'рф',
                'expected' => 'xn--p1ai.example.com',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple update IDN (2)' => [
                'domain' => new Domain('www.bébé.be', new PublicSuffix('be', Rules::ICANN_DOMAINS)),
                'key' => 2,
                'label' => 'xn--p1ai',
                'expected' => 'рф.bébé.be',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'replace a domain with multiple label' => [
                'domain' => $base_domain,
                'key' => -1,
                'label' => 'www.shop',
                'expected' => 'www.shop.example.com',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
        ];
    }

    /**
     * @covers ::withLabel
     */
    public function testWithLabelFailsWithTypeError()
    {
        $this->expectException(InvalidLabel::class);
        (new Domain('example.com'))->withLabel(1, null);
    }

    /**
     * @covers ::withLabel
     */
    public function testWithLabelFailsWithInvalidKey()
    {
        $this->expectException(InvalidLabelKey::class);
        (new Domain('example.com'))->withLabel(-4, 'www');
    }

    /**
     * @covers ::withLabel
     */
    public function testWithLabelFailsWithInvalidLabel2()
    {
        $this->expectException(InvalidDomain::class);
        (new Domain('example.com'))->withLabel(-1, '');
    }

    /**
     * @covers ::append
     * @covers ::withLabel
     *
     * @param string $raw
     * @param string $append
     * @param string $expected
     *
     * @dataProvider validAppend
     */
    public function testAppend($raw, $append, $expected)
    {
        $this->assertSame($expected, (string) (new Domain($raw))->append($append));
    }

    public function validAppend()
    {
        return [
            ['secure.example.com', '8.8.8.8', 'secure.example.com.8.8.8.8'],
            ['secure.example.com', 'master', 'secure.example.com.master'],
            ['secure.example.com', 'master.', 'secure.example.com.master.'],
            ['example.com', '', 'example.com.'],
        ];
    }

    /**
     * @covers ::prepend
     * @covers ::withLabel
     *
     * @param string $raw
     * @param string $prepend
     * @param string $expected
     *
     * @dataProvider validPrepend
     */
    public function testPrepend($raw, $prepend, $expected)
    {
        $this->assertSame($expected, (string) (new Domain($raw))->prepend($prepend));
    }

    public function validPrepend()
    {
        return [
            ['secure.example.com', 'master', 'master.secure.example.com'],
            ['secure.example.com', '127.0.0.1', '127.0.0.1.secure.example.com'],
            ['secure.example.com.', 'master', 'master.secure.example.com.'],
        ];
    }

    /**
     * @covers ::withoutLabel
     * @dataProvider withoutLabelWorksProvider
     *
     * @param null|string $expected
     * @param Domain      $domain
     * @param int         $key
     * @param bool        $isKnown
     * @param bool        $isICANN
     * @param bool        $isPrivate
     */
    public function testwithoutLabelWorks(
        Domain $domain,
        int $key,
        $expected,
        bool $isKnown,
        bool $isICANN,
        bool $isPrivate
    ) {
        $result = $domain->withoutLabel($key);
        $this->assertSame($expected, $result->getContent());
        $this->assertSame($isKnown, $result->isKnown());
        $this->assertSame($isICANN, $result->isICANN());
        $this->assertSame($isPrivate, $result->isPrivate());
    }

    public function withoutLabelWorksProvider()
    {
        $base_domain = new Domain('www.example.com', new PublicSuffix('com', Rules::ICANN_DOMAINS));

        return [
            'simple removal positive offset' => [
                'domain' => $base_domain,
                'key' => 2,
                'expected' => 'example.com',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple removal negative offset' => [
                'domain' => $base_domain,
                'key' => -1,
                'expected' => 'example.com',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple removal strip PSL info positive offset' => [
                'domain' => $base_domain,
                'key' => 0,
                'expected' => 'www.example',
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
            'simple removal strip PSL info negative offset' => [
                'domain' => $base_domain,
                'key' => -3,
                'expected' => 'www.example',
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
        ];
    }

    /**
     * @covers ::withoutLabel
     */
    public function testwithoutLabelFailsWithInvalidKey()
    {
        $this->expectException(InvalidLabelKey::class);
        (new Domain('example.com'))->withoutLabel(-3);
    }

    /**
     * @covers ::withoutLabel
     */
    public function testwithoutLabelWorksWithMultipleKeys()
    {
        $this->assertNull((new Domain('www.example.com'))->withoutLabel(0, 1, 2)->getContent());
    }
}
