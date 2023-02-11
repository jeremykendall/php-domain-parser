<?php

declare(strict_types=1);

namespace Pdp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypeError;
use function date_create;

final class ResolvedDomainTest extends TestCase
{
    public function testItCanBeCreatedWithAnotherResolvedDomain(): void
    {
        $domain = ResolvedDomain::fromICANN('www.github.io', 1);
        $newDomain = ResolvedDomain::fromPrivate($domain, 2);

        self::assertEquals($domain->domain(), $newDomain->domain());
        self::assertNotSame($domain->suffix()->value(), $newDomain->suffix()->value());
    }

    public function testRegistrableDomainIsNullWithFoundDomain(): void
    {
        $domain = ResolvedDomain::fromUnknown('faketld');
        self::assertNull($domain->suffix()->value());
        self::assertNull($domain->registrableDomain()->value());
        self::assertNull($domain->subDomain()->value());
        self::assertNull($domain->secondLevelDomain()->value());
    }

    #[DataProvider('provideWrongConstructor')]
    public function testItThrowsExceptionMisMatchPublicSuffixDomain(?string $domain, int $length): void
    {
        $this->expectException(UnableToResolveDomain::class);

        ResolvedDomain::fromICANN($domain, $length);
    }

    /**
     * @return iterable<string,array{domain:string, length:int}>
     */
    public static function provideWrongConstructor(): iterable
    {
        return [
            'domain and public suffix are the same' => [
                'domain' => 'co.uk',
                'length' => 2,
            ],
            'domain has no labels' => [
                'domain' => 'localhost',
                'length' => 1,
            ],
        ];
    }

    public function testItCanBeUsedWithInternalPhpMethod(): void
    {
        $domain = ResolvedDomain::fromICANN('www.ulb.ac.be', 2);
        /** @var ResolvedDomain $generateDomain */
        $generateDomain = eval('return '.var_export($domain, true).';');
        self::assertEquals($domain, $generateDomain);
        self::assertEquals('"www.ulb.ac.be"', json_encode($domain->jsonSerialize()));
        self::assertSame('www.ulb.ac.be', $domain->toString());
    }

    #[DataProvider('countableProvider')]
    public function testItImplementsCountable(?string $domain, int $nbLabels): void
    {
        self::assertCount($nbLabels, ResolvedDomain::fromUnknown($domain));
    }

    /**
     * @return iterable<string,array{0:string|null, 1:int}>
     */
    public static function countableProvider(): iterable
    {
        return [
            'null' => [null, 0],
            'empty string' => ['', 1],
            'simple' => ['foo.bar.baz', 3],
            'unicode' => ['www.食狮.公司.cn', 4],
        ];
    }

    #[DataProvider('toUnicodeProvider')]
    public function testItCanBeConvertedToUnicode(
        ?string $domain,
        ?string $publicSuffix,
        ?string $expectedDomain,
        ?string $expectedSuffix,
        ?string $expectedIDNDomain,
        ?string $expectedIDNSuffix
    ): void {
        $domain = ResolvedDomain::fromUnknown(Domain::fromIDNA2003($domain), count(Suffix::fromUnknown($publicSuffix)));
        self::assertSame($expectedDomain, $domain->value());
        self::assertSame($expectedSuffix, $domain->suffix()->value());

        /** @var ResolvedDomain $domainIDN */
        $domainIDN = $domain->toUnicode();
        self::assertSame($expectedIDNDomain, $domainIDN->value());
        self::assertSame($expectedIDNSuffix, $domainIDN->suffix()->value());
    }

    /**
     * @return iterable<string,array{domain:string|null, publicSuffix:string|null, expectedDomain:string|null, expectedSuffix:string|null, expectedIDNDomain:string|null, expectedIDNSuffix:string|null}>
     */
    public static function toUnicodeProvider(): iterable
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

    #[DataProvider('toAsciiProvider')]
    public function testItCanBeConvertedToAscii(
        ?string $domain,
        ?string $publicSuffix,
        ?string $expectedDomain,
        ?string $expectedSuffix,
        ?string $expectedAsciiDomain,
        ?string $expectedAsciiSuffix
    ): void {
        $domain = ResolvedDomain::fromUnknown(Domain::fromIDNA2003($domain), count(Domain::fromIDNA2003($publicSuffix)));
        self::assertSame($expectedDomain, $domain->value());
        self::assertSame($expectedSuffix, $domain->suffix()->value());

        /** @var ResolvedDomain $domainIDN */
        $domainIDN = $domain->toAscii();
        self::assertSame($expectedAsciiDomain, $domainIDN->value());
        self::assertSame($expectedAsciiSuffix, $domainIDN->suffix()->value());
    }

    /**
     * @return iterable<string,array{domain:string|null, publicSuffix:string|null, expectedDomain:string|null, expectedSuffix:string|null, expectedIDNDomain:string|null, expectedIDNSuffix:string|null}>
     */
    public static function toAsciiProvider(): iterable
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

    #[DataProvider('withSubDomainWorksProvider')]
    public function testItCanHaveItsSubDomainChanged(ResolvedDomain $domain, DomainName|string|null $subdomain, string $expected = null): void
    {
        $result = $domain->withSubDomain($subdomain);

        self::assertSame($expected, $result->subDomain()->value());
        self::assertEquals($domain->suffix(), $result->suffix());
        self::assertEquals($domain->registrableDomain(), $result->registrableDomain());
    }

    /**
     * @return iterable<string,array{domain:ResolvedDomain, subdomain:DomainName|string|null, expected:string|null}>
     */
    public static function withSubDomainWorksProvider(): iterable
    {
        return [
            'simple addition' => [
                'domain' => ResolvedDomain::fromICANN('example.com', 1),
                'subdomain' => 'www',
                'expected' => 'www',
            ],
            'simple addition IDN (1)' => [
                'domain' => ResolvedDomain::fromICANN(Domain::fromIDNA2003('example.com'), 1),
                'subdomain' => Domain::fromIDNA2003('bébé'),
                'expected' => 'xn--bb-bjab',
            ],
            'simple addition IDN (2)' => [
                'domain' => ResolvedDomain::fromICANN(Domain::fromIDNA2003('Яндекс.РФ'), 1),
                'subdomain' => 'bébé',
                'expected' => 'bébé',
            ],
            'simple removal' => [
                'domain' => ResolvedDomain::fromICANN(Domain::fromIDNA2003('example.com'), 1),
                'subdomain' => null,
                'expected' => null,
            ],
            'simple removal IDN' => [
                'domain' =>  ResolvedDomain::fromICANN(Domain::fromIDNA2003('bébé.Яндекс.РФ'), 1),
                'subdomain' => 'xn--bb-bjab',
                'expected' => 'bébé',
            ],
        ];
    }

    public function testItCanThrowsDuringSubDomainChangesIfItHasNoSuffix(): void
    {
        $this->expectException(UnableToResolveDomain::class);

        ResolvedDomain::fromUnknown(null)->withSubDomain('www');
    }

    public function testItCanThrowsDuringSubDomainChangesIfItHasOnlyOneLabel(): void
    {
        $this->expectException(UnableToResolveDomain::class);

        ResolvedDomain::fromUnknown('localhost')->withSubDomain('www');
    }

    public function testItCanThrowsDuringSubDomainChangesIfTheSubDomainIsTheEmptyString(): void
    {
        $this->expectException(SyntaxError::class);

        ResolvedDomain::fromICANN('www.example.com', 1)->withSubDomain('');
    }

    public function testItCanThrowsDuringSubDomainChangesIfTheSubDomainIsNotStringable(): void
    {
        $this->expectException(TypeError::class);

        ResolvedDomain::fromICANN('www.example.com', 1)->withSubDomain(date_create()); /* @phpstan-ignore-line */
    }

    #[DataProvider('withPublicSuffixWorksProvider')]
    public function testItCanChangeItsSuffix(
        ResolvedDomain $domain,
        EffectiveTopLevelDomain|string|null $publicSuffix,
        ?string $expected,
        bool $isKnown,
        bool $isICANN,
        bool $isPrivate
    ): void {
        $result = $domain->withSuffix($publicSuffix);
        $newPublicSuffix = $result->suffix();

        self::assertSame($expected, $newPublicSuffix->value());
        self::assertSame($isKnown, $newPublicSuffix->isKnown());
        self::assertSame($isICANN, $newPublicSuffix->isICANN());
        self::assertSame($isPrivate, $newPublicSuffix->isPrivate());
    }

    /**
     * @return iterable<string, array{domain:ResolvedDomain, publicSuffix:EffectiveTopLevelDomain|string|null, expected:string|null, isKnown:bool,isICANN:bool, isPrivate:bool}>
     */
    public static function withPublicSuffixWorksProvider(): iterable
    {
        $baseDomain = ResolvedDomain::fromICANN('example.com', 1);

        return [
            'simple update (1)' => [
                'domain' => $baseDomain,
                'publicSuffix' => 'be',
                'expected' => 'be',
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
            'simple update (2)' => [
                'domain' => $baseDomain,
                'publicSuffix' => Suffix::fromPrivate('github.io'),
                'expected' => 'github.io',
                'isKnown' => true,
                'isICANN' => false,
                'isPrivate' => true,
            ],
            'same public suffix but PSL info is changed' => [
                'domain' => $baseDomain,
                'publicSuffix' => Suffix::fromPrivate('com'),
                'expected' => 'com',
                'isKnown' => true,
                'isICANN' => false,
                'isPrivate' => true,
            ],
            'same public suffix but PSL info does not changed' => [
                'domain' => $baseDomain,
                'publicSuffix' => Suffix::fromICANN('com'),
                'expected' => 'com',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple update IDN (1)' => [
                'domain' => $baseDomain,
                'publicSuffix' => Suffix::fromICANN(Domain::fromIDNA2008('рф')),
                'expected' => 'xn--p1ai',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'simple update IDN (2)' => [
                'domain' => ResolvedDomain::fromICANN(Domain::fromIDNA2003('www.bébé.be'), 1),
                'publicSuffix' => Suffix::fromICANN(Domain::fromIDNA2003('xn--p1ai')),
                'expected' => 'рф',
                'isKnown' => true,
                'isICANN' => true,
                'isPrivate' => false,
            ],
            'adding the public suffix to a single label domain' => [
                'domain' => ResolvedDomain::fromUnknown('localhost'),
                'publicSuffix' => 'www',
                'expected' => 'www',
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
            'removing the public suffix list' => [
                'domain' => ResolvedDomain::fromICANN(Domain::fromIDNA2003('www.bébé.be'), 1),
                'publicSuffix' => null,
                'expected' => null,
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
            'with custom IDNA domain options' =>[
                'domain' => ResolvedDomain::fromICANN('www.bébé.be', 1),
                'publicSuffix' => null,
                'expected' => null,
                'isKnown' => false,
                'isICANN' => false,
                'isPrivate' => false,
            ],
        ];
    }

    public function testItCanThrowsDuringSuffixChangesIfTheDomainHasNotSuffix(): void
    {
        $this->expectException(UnableToResolveDomain::class);

        ResolvedDomain::fromUnknown(null)->withSuffix('www');
    }

    #[DataProvider('resolveCustomIDNAOptionsProvider')]
    public function testItCanWorksWithIDNAOptions(
        string $domainName,
        string $publicSuffix,
        string $expectedContent,
        string $expectedAscii,
        string $expectedUnicode,
        string $expectedRegistrable,
        ?string $expectedSubDomain
    ): void {
        $resolvedDomain = ResolvedDomain::fromICANN($domainName, count(Domain::fromIDNA2008($publicSuffix)));

        self::assertSame($expectedContent, $resolvedDomain->value());
        self::assertSame($expectedAscii, $resolvedDomain->toAscii()->value());
        self::assertSame($expectedUnicode, $resolvedDomain->toUnicode()->value());
        self::assertSame($expectedRegistrable, $resolvedDomain->registrableDomain()->value());
        self::assertSame($expectedSubDomain, $resolvedDomain->subDomain()->value());
    }

    /**
     * @return iterable<string,array{0:string, 1:string, 2:string, 3:string, 4:string, 5:string, 6:string|null}>
     */
    public static function resolveCustomIDNAOptionsProvider(): iterable
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

    #[DataProvider('withSldWorksProvider')]
    public function testWithSecondLevelDomain(
        ?string $host,
        ?string $publicSuffix,
        ?string $sld,
        ?string $expectedSld,
        ?string $expectedHost
    ): void {
        $domain = ResolvedDomain::fromICANN($host, count(Domain::fromIDNA2008($publicSuffix)));
        $newDomain = $domain->withSecondLevelDomain($sld);

        self::assertSame($expectedSld, $newDomain->secondLevelDomain()->value());
        self::assertEquals($expectedHost, $newDomain->value());
        self::assertEquals($domain->suffix(), $newDomain->suffix());
        self::assertEquals($domain->subDomain(), $newDomain->subDomain());
    }

    /**
     * @return iterable<array-key, array{host:string|null, publicSuffix:string|null, sld:string|null, expectedSld:string|null, expectedHost:string|null}>
     */
    public static function withSldWorksProvider(): iterable
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

    public function testItCanNotAppendAnEmptySLD(): void
    {
        $this->expectException(UnableToResolveDomain::class);

        ResolvedDomain::fromICANN('private.ulb.ac.be', 2)->withSecondLevelDomain(null);
    }

    public function testItCanNotAppendASLDToAResolvedDomainWithoutSuffix(): void
    {
        $this->expectException(UnableToResolveDomain::class);

        ResolvedDomain::fromUnknown('private.ulb.ac.be')->withSecondLevelDomain('yes');
    }

    public function testItCanNotAppendAnInvalidSLDToAResolvedDomain(): void
    {
        $this->expectException(UnableToResolveDomain::class);

        ResolvedDomain::fromIANA('private.ulb.ac.be')->withSecondLevelDomain('foo.bar');
    }

    public function testItReturnsTheInstanceWhenTheSLDIsEqual(): void
    {
        $domain = ResolvedDomain::fromICANN('private.ulb.ac.be', 2);

        self::assertEquals($domain->withSecondLevelDomain('ulb'), $domain);
    }
}
