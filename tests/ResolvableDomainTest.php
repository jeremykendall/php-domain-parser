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
use Pdp\InvalidDomain;
use Pdp\PublicSuffix;
use Pdp\ResolvableDomain;
use Pdp\UnableToResolveDomain;
use PHPUnit\Framework\TestCase;
use TypeError;
use function date_create;
use const IDNA_NONTRANSITIONAL_TO_ASCII;
use const IDNA_NONTRANSITIONAL_TO_UNICODE;

/**
 * @coversDefaultClass \Pdp\ResolvableDomain
 */
class ResolvableDomainTest extends TestCase
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
    public function testRegistrableDomainIsNullWithFoundDomain(): void
    {
        $domain = new ResolvableDomain(new Domain('faketld'));
        self::assertNull($domain->getPublicSuffix()->getContent());
        self::assertNull($domain->getRegistrableDomain()->getContent());
        self::assertNull($domain->getSubDomain()->getContent());
    }

    /**
     * @covers ::__construct
     * @covers ::setPublicSuffix
     * @covers ::normalize
     * @dataProvider provideWrongConstructor
     *
     * @param ?string $domain
     */
    public function testConstructorThrowsExceptionOnMisMatchPublicSuffixDomain(?string $domain, string $publicSuffix): void
    {
        self::expectException(UnableToResolveDomain::class);

        new ResolvableDomain(new Domain($domain), PublicSuffix::fromICANNSection($publicSuffix));
    }

    public function provideWrongConstructor(): iterable
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
     * @covers ::__construct
     * @covers ::__set_state
     * @covers ::__toString
     * @covers ::jsonSerialize
     */
    public function testDomainInternalPhpMethod(): void
    {
        $domain = new ResolvableDomain(new Domain('www.ulb.ac.be'), PublicSuffix::fromICANNSection('ac.be'));
        $generateDomain = eval('return '.var_export($domain, true).';');
        self::assertEquals($domain, $generateDomain);
        self::assertEquals('"www.ulb.ac.be"', json_encode($domain->jsonSerialize()));
        self::assertSame('www.ulb.ac.be', (string) $domain);
    }

    /**
     * @covers ::normalize
     * @covers ::count
     * @dataProvider countableProvider
     * @param ?string $domain
     */
    public function testCountable(?string $domain, int $nbLabels): void
    {
        $domain = new Domain($domain);
        self::assertCount($nbLabels, $domain);
    }

    public function countableProvider(): iterable
    {
        return [
            'null' => [null, 0],
            'empty string' => ['', 1],
            'simple' => ['foo.bar.baz', 3],
            'unicode' => ['www.食狮.公司.cn', 4],
        ];
    }

    /**
     * @covers ::setPublicSuffix
     * @covers ::normalize
     * @covers ::setRegistrableDomain
     * @covers ::setSubDomain
     * @covers ::getContent
     * @covers ::getPublicSuffix
     * @covers ::toUnicode
     * @covers \Pdp\PublicSuffix::toUnicode
     * @dataProvider toUnicodeProvider
     * @param ?string $domain
     * @param ?string $publicSuffix
     * @param ?string $expectedDomain
     * @param ?string $expectedSuffix
     * @param ?string $expectedIDNDomain
     * @param ?string $expectedIDNSuffix
     */
    public function testToIDN(
        ?string $domain,
        ?string $publicSuffix,
        ?string $expectedDomain,
        ?string $expectedSuffix,
        ?string $expectedIDNDomain,
        ?string $expectedIDNSuffix
    ): void {
        $objPublicSuffix = (null === $publicSuffix) ? PublicSuffix::fromNull() : PublicSuffix::fromICANNSection($publicSuffix);

        $domain = new ResolvableDomain(new Domain($domain), $objPublicSuffix);
        self::assertSame($expectedDomain, $domain->getContent());
        self::assertSame($expectedSuffix, $domain->getPublicSuffix()->getContent());

        /** @var ResolvableDomain $domainIDN */
        $domainIDN = $domain->toUnicode();
        self::assertSame($expectedIDNDomain, $domainIDN->getContent());
        self::assertSame($expectedIDNSuffix, $domainIDN->getPublicSuffix()->getContent());
    }

    public function toUnicodeProvider(): iterable
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
     * @covers ::setPublicSuffix
     * @covers ::normalize
     * @covers ::setRegistrableDomain
     * @covers ::setSubDomain
     * @covers ::getContent
     * @covers ::getPublicSuffix
     * @covers ::toAscii
     * @covers \Pdp\PublicSuffix::toAscii
     *
     * @dataProvider toAsciiProvider
     * @param ?string $domain
     * @param ?string $publicSuffix
     * @param ?string $expectedDomain
     * @param ?string $expectedSuffix
     * @param ?string $expectedAsciiDomain
     * @param ?string $expectedAsciiSuffix
     */
    public function testToAscii(
        ?string $domain,
        ?string $publicSuffix,
        ?string $expectedDomain,
        ?string $expectedSuffix,
        ?string $expectedAsciiDomain,
        ?string $expectedAsciiSuffix
    ): void {
        $objPublicSuffix = (null === $publicSuffix) ? PublicSuffix::fromNull() : PublicSuffix::fromICANNSection($publicSuffix);

        $domain = new ResolvableDomain(new Domain($domain), $objPublicSuffix);
        self::assertSame($expectedDomain, $domain->getContent());
        self::assertSame($expectedSuffix, $domain->getPublicSuffix()->getContent());

        /** @var ResolvableDomain $domainIDN */
        $domainIDN = $domain->toAscii();
        self::assertSame($expectedAsciiDomain, $domainIDN->getContent());
        self::assertSame($expectedAsciiSuffix, $domainIDN->getPublicSuffix()->getContent());
    }

    public function toAsciiProvider(): iterable
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
     * @param mixed   $publicSuffix the public suffix to resolve
     * @param ?string $expected
     */
    public function testResolveWorks(ResolvableDomain $domain, $publicSuffix, ?string $expected): void
    {
        self::assertSame($expected, $domain->resolve($publicSuffix)->getPublicSuffix()->getContent());
    }

    public function resolvePassProvider(): iterable
    {
        $publicSuffix = PublicSuffix::fromICANNSection('ac.be');
        $domain = new ResolvableDomain(new Domain('ulb.ac.be'), $publicSuffix);

        return [
            'null public suffix' => [
                'domain' => $domain,
                'public suffix' => PublicSuffix::fromNull(),
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
                'public suffix' => PublicSuffix::fromICANNSection('be'),
                'expected' => 'be',
            ],
            'idn domain name' => [
                'domain' => new ResolvableDomain(new Domain('Яндекс.РФ'), PublicSuffix::fromICANNSection('рф')),
                'public suffix' => PublicSuffix::fromICANNSection('рф'),
                'expected' => 'рф',
            ],
            'idn domain name with ascii public suffix' => [
                'domain' => new ResolvableDomain(new Domain('Яндекс.РФ'), PublicSuffix::fromICANNSection('рф')),
                'public suffix' => PublicSuffix::fromICANNSection('xn--p1ai'),
                'expected' => 'рф',
            ],
        ];
    }

    /**
     * @covers ::resolve
     * @dataProvider resolveFailsProvider
     */
    public function testResolveFails(ResolvableDomain $domain, PublicSuffix $publicSuffix): void
    {
        self::expectException(UnableToResolveDomain::class);
        $domain->resolve($publicSuffix);
    }

    public function resolveFailsProvider(): iterable
    {
        $publicSuffix = PublicSuffix::fromICANNSection('ac.be');
        $domain = new ResolvableDomain(new Domain('ulb.ac.be'), $publicSuffix);

        return [
            'public suffix mismatch' => [
                'domain' => $domain,
                'public suffix' => PublicSuffix::fromICANNSection('ac.fr'),
            ],
            'domain name can not contains public suffix' => [
                'domain' => new ResolvableDomain(new Domain('localhost')),
                'public suffix' => $publicSuffix,
            ],
            'domain name is equal to public suffix' => [
                'domain' => new ResolvableDomain(new Domain('ac.be')),
                'public suffix' => $publicSuffix,
            ],
            'partial public suffix' => [
                'domain' => new ResolvableDomain($domain),
                'public suffix' => PublicSuffix::fromICANNSection('c.be'),
            ],
            'mismatch idn public suffix' => [
                'domain' => new ResolvableDomain(new Domain('www.食狮.公司.cn')),
                'public suffix' => PublicSuffix::fromICANNSection('cn.公司'),
            ],
        ];
    }

    /**
     * @covers ::resolve
     */
    public function testResolveReturnsInstance(): void
    {
        $publicSuffix = PublicSuffix::fromICANNSection('ac.be');
        $domain = new ResolvableDomain(new Domain('ulb.ac.be'), $publicSuffix);
        self::assertEquals($domain, $domain->resolve($publicSuffix));
        self::assertNotSame($domain, $domain->resolve(PublicSuffix::fromPrivateSection('ac.be')));
    }

    /**
     * @covers ::withSubDomain
     * @dataProvider withSubDomainWorksProvider
     *
     * @param mixed   $subdomain the subdomain to add
     * @param ?string $expected
     */
    public function testWithSubDomainWorks(ResolvableDomain $domain, $subdomain, ?string $expected): void
    {
        $result = $domain->withSubDomain($subdomain);

        self::assertSame($expected, $result->getSubDomain()->getContent());
        self::assertEquals($domain->getPublicSuffix(), $result->getPublicSuffix());
        self::assertEquals($domain->getRegistrableDomain(), $result->getRegistrableDomain());
    }

    public function withSubDomainWorksProvider(): iterable
    {
        return [
            'simple addition' => [
                'domain' => new ResolvableDomain(new Domain('example.com'), PublicSuffix::fromICANNSection('com')),
                'subdomain' => 'www',
                'expected' => 'www',
            ],
            'simple addition IDN (1)' => [
                'domain' => new ResolvableDomain(new Domain('example.com'), PublicSuffix::fromICANNSection('com')),
                'subdomain' => new Domain('bébé'),
                'expected' => 'xn--bb-bjab',
            ],
            'simple addition IDN (2)' => [
                'domain' => new ResolvableDomain(new Domain('Яндекс.РФ'), PublicSuffix::fromICANNSection('рф')),
                'subdomain' => 'bébé',
                'expected' => 'bébé',
            ],
            'simple removal' => [
                'domain' => new ResolvableDomain(new Domain('example.com'), PublicSuffix::fromICANNSection('com')),
                'subdomain' => null,
                'expected' => null,
            ],
            'simple removal IDN' => [
                'domain' =>  new ResolvableDomain(new Domain('bébé.Яндекс.РФ'), PublicSuffix::fromICANNSection('рф')),
                'subdomain' => 'xn--bb-bjab',
                'expected' => 'bébé',
            ],
        ];
    }

    /**
     * @covers ::withSubDomain
     */
    public function testWithSubDomainFailsWithNullDomain(): void
    {
        self::expectException(UnableToResolveDomain::class);

        (new ResolvableDomain(new Domain(null)))->withSubDomain('www');
    }

    /**
     * @covers ::withSubDomain
     */
    public function testWithSubDomainFailsWithOneLabelDomain(): void
    {
        self::expectException(UnableToResolveDomain::class);

        (new ResolvableDomain(new Domain('localhost')))->withSubDomain('www');
    }

    /**
     * @covers ::withSubDomain
     */
    public function testWithEmptySubdomain(): void
    {
        self::expectException(InvalidDomain::class);

        $domain = new ResolvableDomain(new Domain('www.example.com'), PublicSuffix::fromICANNSection('com'));
        $domain->withSubDomain('');
    }

    /**
     * @covers ::withSubDomain
     */
    public function testWithSubDomainFailsWithNonStringableObject(): void
    {
        self::expectException(TypeError::class);
        $domain = new ResolvableDomain(new Domain('www.example.com'), PublicSuffix::fromICANNSection('com'));

        $domain->withSubDomain(date_create());
    }

    /**
     * @covers ::withSubDomain
     */
    public function testWithSubDomainWithoutPublicSuffixInfo(): void
    {
        self::expectException(UnableToResolveDomain::class);

        (new ResolvableDomain(new Domain('www.example.com')))->withSubDomain('www');
    }

    /**
     * @covers ::withPublicSuffix
     * @dataProvider withPublicSuffixWorksProvider
     *
     * @param mixed   $publicSuffix the public suffix
     * @param ?string $expected
     */
    public function testWithPublicSuffixWorks(
        ResolvableDomain $domain,
        $publicSuffix,
        ?string $expected,
        bool $isKnown,
        bool $isICANN,
        bool $isPrivate
    ): void {
        $result = $domain->withPublicSuffix($publicSuffix);

        self::assertSame($expected, $result->getPublicSuffix()->getContent());
    }

    public function withPublicSuffixWorksProvider(): iterable
    {
        $base_domain = new ResolvableDomain(new Domain('example.com'), PublicSuffix::fromICANNSection('com'));

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
                'publicSuffix' => PublicSuffix::fromPrivateSection('github.io'),
                'expected' => 'github.io',
                'isKnown' => true,
                'isICANN' => false,
                'isPrivate' => true,
            ],
            'same public suffix but PSL info is changed' => [
                'domain' => $base_domain,
                'publicSuffix' => PublicSuffix::fromPrivateSection('com'),
                'expected' => 'com',
                'isKnown' => true,
                'isICANN' => false,
                'isPrivate' => true,
            ],
            'same public suffix but PSL info does not changed' => [
                'domain' => $base_domain,
                'publicSuffix' => PublicSuffix::fromICANNSection('com'),
                'expected' => 'com',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple update IDN (1)' => [
                'domain' => $base_domain,
                'publicSuffix' => PublicSuffix::fromICANNSection('рф'),
                'expected' => 'xn--p1ai',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple update IDN (2)' => [
                'domain' => new ResolvableDomain(new Domain('www.bébé.be'), PublicSuffix::fromICANNSection('be')),
                'publicSuffix' => PublicSuffix::fromICANNSection('xn--p1ai'),
                'expected' => 'рф',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'adding the public suffix to a single label domain' => [
                'domain' => new ResolvableDomain(new Domain('localhost')),
                'publicSuffix' => 'www',
                'expected' => 'www',
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
            'removing the public suffix list' => [
                'domain' => new ResolvableDomain(new Domain('www.bébé.be'), PublicSuffix::fromICANNSection('be')),
                'publicSuffix' => null,
                'expected' => null,
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
            'with custom IDNA domain options' =>[
                'domain' => new ResolvableDomain(new Domain('www.bébé.be', IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE), PublicSuffix::fromICANNSection('be')),
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
    public function testWithPublicSuffixFailsWithNullDomain(): void
    {
        self::expectException(InvalidDomain::class);

        (new ResolvableDomain(new Domain()))->withPublicSuffix('www');
    }

    /**
     * @dataProvider resolveCustomIDNAOptionsProvider
     * @param ?string $expectedContent
     * @param ?string $expectedAscii
     * @param ?string $expectedUnicode
     * @param ?string $expectedRegistrable
     * @param ?string $expectedSubDomain
     * @param ?string $expectedWithLabel
     */
    public function testResolveWorksWithCustomIDNAOptions(
        string $domainName,
        string $publicSuffix,
        string $withLabel,
        ?string $expectedContent,
        ?string $expectedAscii,
        ?string $expectedUnicode,
        ?string $expectedRegistrable,
        ?string $expectedSubDomain,
        ?string $expectedWithLabel
    ): void {
        $domainHost = new Domain($domainName, IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE);
        $domain = new ResolvableDomain($domainHost, PublicSuffix::fromICANNSection($publicSuffix));

        self::assertSame($expectedContent, $domain->getContent());
        self::assertSame($expectedAscii, $domain->toAscii()->getContent());
        self::assertSame($expectedUnicode, $domain->toUnicode()->getContent());
        self::assertSame($expectedRegistrable, $domain->getRegistrableDomain()->getContent());
        self::assertSame($expectedSubDomain, $domain->getSubDomain()->getContent());
    }

    public function resolveCustomIDNAOptionsProvider(): iterable
    {
        return [
            'without deviation characters' => [
                'example.com',
                'com',
                'größe',
                'example.com',
                'example.com',
                'example.com',
                'example.com',
                 null,
                'xn--gre-6ka8i.com',
            ],
            'without deviation characters with label' => [
                'www.example.com',
                'com',
                'größe',
                'www.example.com',
                'www.example.com',
                'www.example.com',
                'example.com',
                'www',
                'xn--gre-6ka8i.example.com',
            ],
            'with deviation in domain' => [
                'www.faß.de',
                'de',
                'größe',
                'www.faß.de',
                'www.xn--fa-hia.de',
                'www.faß.de',
                'faß.de',
                'www',
                'größe.faß.de',
            ],
            'with deviation in label' => [
                'faß.test.de',
                'de',
                'größe',
                'faß.test.de',
                'xn--fa-hia.test.de',
                'faß.test.de',
                'test.de',
                'faß',
                'größe.test.de',
            ],
        ];
    }

    public function testInstanceCreationWithCustomIDNAOptions(): void
    {
        $domain = new ResolvableDomain(
            new Domain('example.com', IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE),
            PublicSuffix::fromICANNSection('com')
        );

        /** @var ResolvableDomain $instance */
        $instance = $domain->toAscii();
        self::assertSame(
            [$domain->getAsciiIDNAOption(), $domain->getUnicodeIDNAOption()],
            [$instance->getAsciiIDNAOption(), $instance->getUnicodeIDNAOption()]
        );

        /** @var ResolvableDomain $instance */
        $instance = $domain->toUnicode();
        self::assertSame(
            [$domain->getAsciiIDNAOption(), $domain->getUnicodeIDNAOption()],
            [$instance->getAsciiIDNAOption(), $instance->getUnicodeIDNAOption()]
        );

        $instance = $domain->withPublicSuffix(PublicSuffix::fromICANNSection('us'));
        self::assertSame(
            [$domain->getAsciiIDNAOption(), $domain->getUnicodeIDNAOption()],
            [$instance->getAsciiIDNAOption(), $instance->getUnicodeIDNAOption()]
        );
        $instance = $domain->withSubDomain(new Domain('foo'));
        self::assertSame(
            [$domain->getAsciiIDNAOption(), $domain->getUnicodeIDNAOption()],
            [$instance->getAsciiIDNAOption(), $instance->getUnicodeIDNAOption()]
        );

        $instance = $domain->resolve(PublicSuffix::fromICANNSection('com'));
        self::assertSame(
            [$domain->getAsciiIDNAOption(), $domain->getUnicodeIDNAOption()],
            [$instance->getAsciiIDNAOption(), $instance->getUnicodeIDNAOption()]
        );
    }
}
