<?php

declare(strict_types=1);

namespace Pdp;

use PHPUnit\Framework\TestCase;
use TypeError;
use function date_create;
use const IDNA_NONTRANSITIONAL_TO_ASCII;
use const IDNA_NONTRANSITIONAL_TO_UNICODE;

/**
 * @coversDefaultClass \Pdp\ResolvedDomain
 */
class ResolvedDomainTest extends TestCase
{
    public function testRegistrableDomainIsNullWithFoundDomain(): void
    {
        $domain = new ResolvedDomain(new Domain('faketld'));
        self::assertNull($domain->getPublicSuffix()->value());
        self::assertNull($domain->getRegistrableDomain()->value());
        self::assertNull($domain->getSubDomain()->value());
        self::assertNull($domain->getSecondLevelDomain());
    }

    /**
     * @dataProvider provideWrongConstructor
     * @param ?string $domain
     */
    public function testConstructorThrowsExceptionOnMisMatchPublicSuffixDomain(?string $domain, string $publicSuffix): void
    {
        self::expectException(UnableToResolveDomain::class);

        new ResolvedDomain(new Domain($domain), PublicSuffix::fromICANN($publicSuffix));
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

    public function testDomainInternalPhpMethod(): void
    {
        $domain = new ResolvedDomain(new Domain('www.ulb.ac.be'), PublicSuffix::fromICANN('ac.be'));
        $generateDomain = eval('return '.var_export($domain, true).';');
        self::assertEquals($domain, $generateDomain);
        self::assertEquals('"www.ulb.ac.be"', json_encode($domain->jsonSerialize()));
        self::assertSame('www.ulb.ac.be', $domain->toString());
    }

    /**
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
        $objPublicSuffix = (null === $publicSuffix) ? PublicSuffix::fromNull() : PublicSuffix::fromICANN($publicSuffix);

        $domain = new ResolvedDomain(new Domain($domain), $objPublicSuffix);
        self::assertSame($expectedDomain, $domain->value());
        self::assertSame($expectedSuffix, $domain->getPublicSuffix()->value());

        /** @var ResolvedDomain $domainIDN */
        $domainIDN = $domain->toUnicode();
        self::assertSame($expectedIDNDomain, $domainIDN->value());
        self::assertSame($expectedIDNSuffix, $domainIDN->getPublicSuffix()->value());
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
        $objPublicSuffix = (null === $publicSuffix) ? PublicSuffix::fromNull() : PublicSuffix::fromICANN($publicSuffix);

        $domain = new ResolvedDomain(new Domain($domain), $objPublicSuffix);
        self::assertSame($expectedDomain, $domain->value());
        self::assertSame($expectedSuffix, $domain->getPublicSuffix()->value());

        /** @var ResolvedDomain $domainIDN */
        $domainIDN = $domain->toAscii();
        self::assertSame($expectedAsciiDomain, $domainIDN->value());
        self::assertSame($expectedAsciiSuffix, $domainIDN->getPublicSuffix()->value());
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
     * @dataProvider withSubDomainWorksProvider
     *
     * @param mixed   $subdomain the subdomain to add
     * @param ?string $expected
     */
    public function testWithSubDomainWorks(ResolvedDomain $domain, $subdomain, ?string $expected): void
    {
        $result = $domain->withSubDomain($subdomain);

        self::assertSame($expected, $result->getSubDomain()->value());
        self::assertEquals($domain->getPublicSuffix(), $result->getPublicSuffix());
        self::assertEquals($domain->getRegistrableDomain(), $result->getRegistrableDomain());
    }

    public function withSubDomainWorksProvider(): iterable
    {
        return [
            'simple addition' => [
                'domain' => new ResolvedDomain(new Domain('example.com'), PublicSuffix::fromICANN('com')),
                'subdomain' => 'www',
                'expected' => 'www',
            ],
            'simple addition IDN (1)' => [
                'domain' => new ResolvedDomain(new Domain('example.com'), PublicSuffix::fromICANN('com')),
                'subdomain' => new Domain('bébé'),
                'expected' => 'xn--bb-bjab',
            ],
            'simple addition IDN (2)' => [
                'domain' => new ResolvedDomain(new Domain('Яндекс.РФ'), PublicSuffix::fromICANN('рф')),
                'subdomain' => 'bébé',
                'expected' => 'bébé',
            ],
            'simple removal' => [
                'domain' => new ResolvedDomain(new Domain('example.com'), PublicSuffix::fromICANN('com')),
                'subdomain' => null,
                'expected' => null,
            ],
            'simple removal IDN' => [
                'domain' =>  new ResolvedDomain(new Domain('bébé.Яндекс.РФ'), PublicSuffix::fromICANN('рф')),
                'subdomain' => 'xn--bb-bjab',
                'expected' => 'bébé',
            ],
        ];
    }

    public function testWithSubDomainFailsWithNullDomain(): void
    {
        self::expectException(UnableToResolveDomain::class);

        (new ResolvedDomain(new Domain(null)))->withSubDomain('www');
    }

    public function testWithSubDomainFailsWithOneLabelDomain(): void
    {
        self::expectException(UnableToResolveDomain::class);

        (new ResolvedDomain(new Domain('localhost')))->withSubDomain('www');
    }

    public function testWithEmptySubdomain(): void
    {
        self::expectException(SyntaxError::class);

        $domain = new ResolvedDomain(new Domain('www.example.com'), PublicSuffix::fromICANN('com'));

        $domain->withSubDomain('');
    }

    public function testWithSubDomainFailsWithNonStringableObject(): void
    {
        self::expectException(TypeError::class);
        $domain = new ResolvedDomain(new Domain('www.example.com'), PublicSuffix::fromICANN('com'));

        $domain->withSubDomain(date_create());
    }

    public function testWithSubDomainWithoutPublicSuffixInfo(): void
    {
        self::expectException(UnableToResolveDomain::class);

        (new ResolvedDomain(new Domain('www.example.com')))->withSubDomain('www');
    }

    /**
     * @dataProvider withPublicSuffixWorksProvider
     *
     * @param mixed   $publicSuffix the public suffix
     * @param ?string $expected
     */
    public function testWithPublicSuffixWorks(
        ResolvedDomain $domain,
        $publicSuffix,
        ?string $expected,
        bool $isKnown,
        bool $isICANN,
        bool $isPrivate
    ): void {
        $result = $domain->withPublicSuffix($publicSuffix);
        $newPublicSuffix = $result->getPublicSuffix();

        self::assertSame($expected, $newPublicSuffix->value());
        self::assertSame($isKnown, $newPublicSuffix->isKnown());
        self::assertSame($isICANN, $newPublicSuffix->isICANN());
        self::assertSame($isPrivate, $newPublicSuffix->isPrivate());
    }

    public function withPublicSuffixWorksProvider(): iterable
    {
        $base_domain = new ResolvedDomain(new Domain('example.com'), PublicSuffix::fromICANN('com'));

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
                'publicSuffix' => PublicSuffix::fromPrivate('github.io'),
                'expected' => 'github.io',
                'isKnown' => true,
                'isICANN' => false,
                'isPrivate' => true,
            ],
            'same public suffix but PSL info is changed' => [
                'domain' => $base_domain,
                'publicSuffix' => PublicSuffix::fromPrivate('com'),
                'expected' => 'com',
                'isKnown' => true,
                'isICANN' => false,
                'isPrivate' => true,
            ],
            'same public suffix but PSL info does not changed' => [
                'domain' => $base_domain,
                'publicSuffix' => PublicSuffix::fromICANN('com'),
                'expected' => 'com',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple update IDN (1)' => [
                'domain' => $base_domain,
                'publicSuffix' => PublicSuffix::fromICANN('рф'),
                'expected' => 'xn--p1ai',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple update IDN (2)' => [
                'domain' => new ResolvedDomain(new Domain('www.bébé.be'), PublicSuffix::fromICANN('be')),
                'publicSuffix' => PublicSuffix::fromICANN('xn--p1ai'),
                'expected' => 'рф',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'adding the public suffix to a single label domain' => [
                'domain' => new ResolvedDomain(new Domain('localhost')),
                'publicSuffix' => 'www',
                'expected' => 'www',
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
            'removing the public suffix list' => [
                'domain' => new ResolvedDomain(new Domain('www.bébé.be'), PublicSuffix::fromICANN('be')),
                'publicSuffix' => null,
                'expected' => null,
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
            'with custom IDNA domain options' =>[
                'domain' => new ResolvedDomain(new Domain('www.bébé.be', IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE), PublicSuffix::fromICANN('be')),
                'publicSuffix' => null,
                'expected' => null,
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
        ];
    }

    public function testWithPublicSuffixFailsWithNullDomain(): void
    {
        self::expectException(SyntaxError::class);

        (new ResolvedDomain(new Domain()))->withPublicSuffix('www');
    }

    /**
     * @dataProvider resolveCustomIDNAOptionsProvider
     * @param ?string $expectedContent
     * @param ?string $expectedAscii
     * @param ?string $expectedUnicode
     * @param ?string $expectedRegistrable
     * @param ?string $expectedSubDomain
     */
    public function testResolveWorksWithCustomIDNAOptions(
        string $domainName,
        string $publicSuffix,
        ?string $expectedContent,
        ?string $expectedAscii,
        ?string $expectedUnicode,
        ?string $expectedRegistrable,
        ?string $expectedSubDomain
    ): void {
        $host = new Domain($domainName, IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE);
        $resolvedDomain = new ResolvedDomain($host, PublicSuffix::fromICANN($publicSuffix));

        self::assertSame($expectedContent, $resolvedDomain->value());
        self::assertSame($expectedAscii, $resolvedDomain->toAscii()->value());
        self::assertSame($expectedUnicode, $resolvedDomain->toUnicode()->value());
        self::assertSame($expectedRegistrable, $resolvedDomain->getRegistrableDomain()->value());
        self::assertSame($expectedSubDomain, $resolvedDomain->getSubDomain()->value());
    }

    public function resolveCustomIDNAOptionsProvider(): iterable
    {
        return [
            'without deviation characters' => [
                'example.com',
                'com',
                'example.com',
                'example.com',
                'example.com',
                'example.com',
                 null,
            ],
            'without deviation characters with label' => [
                'www.example.com',
                'com',
                'www.example.com',
                'www.example.com',
                'www.example.com',
                'example.com',
                'www',
            ],
            'with deviation in domain' => [
                'www.faß.de',
                'de',
                'www.faß.de',
                'www.xn--fa-hia.de',
                'www.faß.de',
                'faß.de',
                'www',
            ],
            'with deviation in label' => [
                'faß.test.de',
                'de',
                'faß.test.de',
                'xn--fa-hia.test.de',
                'faß.test.de',
                'test.de',
                'faß',
            ],
        ];
    }

    public function testInstanceCreationWithCustomIDNAOptions(): void
    {
        $domain = new ResolvedDomain(
            new Domain('example.com', IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE),
            PublicSuffix::fromICANN('com')
        );

        /** @var ResolvedDomain $instance */
        $instance = $domain->toAscii();
        self::assertSame(
            [$domain->getDomain()->getAsciiIDNAOption(), $domain->getDomain()->getUnicodeIDNAOption()],
            [$instance->getDomain()->getAsciiIDNAOption(), $instance->getDomain()->getUnicodeIDNAOption()]
        );

        /** @var ResolvedDomain $instance */
        $instance = $domain->toUnicode();

        self::assertSame(
            [$domain->getDomain()->getAsciiIDNAOption(), $domain->getDomain()->getUnicodeIDNAOption()],
            [$instance->getDomain()->getAsciiIDNAOption(), $instance->getDomain()->getUnicodeIDNAOption()]
        );

        $instance = $domain->withPublicSuffix(PublicSuffix::fromICANN('us'));

        self::assertSame(
            [$domain->getDomain()->getAsciiIDNAOption(), $domain->getDomain()->getUnicodeIDNAOption()],
            [$instance->getDomain()->getAsciiIDNAOption(), $instance->getDomain()->getUnicodeIDNAOption()]
        );

        $instance = $domain->withSubDomain(new Domain('foo'));

        self::assertSame(
            [$domain->getDomain()->getAsciiIDNAOption(), $domain->getDomain()->getUnicodeIDNAOption()],
            [$instance->getDomain()->getAsciiIDNAOption(), $instance->getDomain()->getUnicodeIDNAOption()]
        );
    }

    /**
     * @dataProvider withSldWorksProvider
     * @param ?string $host
     * @param ?string $publicSuffix
     * @param ?string $sld
     * @param ?string $expectedSld
     * @param ?string $expectedHost
     */
    public function testWithSecondLevelDomain(
        ?string $host,
        ?string $publicSuffix,
        ?string $sld,
        ?string $expectedSld,
        ?string $expectedHost
    ): void {
        $domain = new ResolvedDomain(new Domain($host), PublicSuffix::fromICANN($publicSuffix));
        $newDomain = $domain->withSecondLevelDomain($sld);

        self::assertSame($expectedSld, $newDomain->getSecondLevelDomain());
        self::assertEquals($expectedHost, $newDomain->value());
        self::assertEquals($domain->getPublicSuffix(), $newDomain->getPublicSuffix());
        self::assertEquals($domain->getSubDomain(), $newDomain->getSubDomain());
    }

    public function withSldWorksProvider(): iterable
    {
        return [
            [
                'host' => 'example.com',
                'publicSuffix' => 'com',
                'sld' => 'www',
                'expectedSld' => 'www',
                'expectedHost' => 'www.com',
            ],
            [
                'host' => 'www.example.com',
                'publicSuffix' => 'com',
                'sld' => 'www',
                'expectedSld' => 'www',
                'expectedHost' => 'www.www.com',
            ],
            [
                'host' => 'www.bbc.co.uk',
                'publicSuffix' => 'co.uk',
                'sld' => 'hamburger',
                'expectedSld' => 'hamburger',
                'expectedHost' => 'www.hamburger.co.uk',
            ],
        ];
    }
}
