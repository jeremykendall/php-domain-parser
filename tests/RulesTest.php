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
use Pdp\InvalidDomainName;
use Pdp\InvalidHost;
use Pdp\PublicSuffix;
use Pdp\ResolvedDomain;
use Pdp\Rules;
use Pdp\UnableToLoadPublicSuffixList;
use Pdp\UnableToResolveDomain;
use PHPUnit\Framework\TestCase;
use TypeError;
use function array_fill;
use function file_get_contents;
use function implode;
use const IDNA_DEFAULT;
use const IDNA_NONTRANSITIONAL_TO_ASCII;
use const IDNA_NONTRANSITIONAL_TO_UNICODE;

/**
 * @coversDefaultClass \Pdp\Rules
 */
final class RulesTest extends TestCase
{
    /**
     * @var Rules
     */
    private $rules;

    public function setUp(): void
    {
        /** @var string $string */
        $string = file_get_contents(__DIR__.'/data/public_suffix_list.dat');

        $this->rules = Rules::fromString($string);
    }

    /**
     * @covers ::fromPath
     * @covers ::fromString
     * @covers ::__construct
     */
    public function testCreateFromPath(): void
    {
        /** @var string $string */
        $string = file_get_contents(__DIR__.'/data/public_suffix_list.dat');
        $rulesFromString = Rules::fromString($string);

        $context = stream_context_create([
            'http'=> [
                'method' => 'GET',
                'header' => "Accept-language: en\r\nCookie: foo=bar\r\n",
            ],
        ]);
        $rulesFromPath = Rules::fromPath(__DIR__.'/data/public_suffix_list.dat', $context);

        self::assertEquals($rulesFromString, $rulesFromPath);
    }

    /**
     * @covers ::fromPath
     */
    public function testCreateFromPathThrowsException(): void
    {
        self::expectException(UnableToLoadPublicSuffixList::class);
        Rules::fromPath('/foo/bar.dat');
    }

    /**
     * @covers ::__set_state
     * @covers ::__construct
     */
    public function testDomainInternalPhpMethod(): void
    {
        $generateRules = eval('return '.var_export($this->rules, true).';');
        self::assertEquals($this->rules, $generateRules);
    }

    /**
     * @covers ::getAsciiIDNAOption
     * @covers ::getUnicodeIDNAOption
     * @covers ::withAsciiIDNAOption
     * @covers ::withUnicodeIDNAOption
     */
    public function testwithIDNAOptions(): void
    {
        self::assertSame($this->rules, $this->rules->withAsciiIDNAOption(
            $this->rules->getAsciiIDNAOption()
        ));

        self::assertNotEquals($this->rules, $this->rules->withAsciiIDNAOption(
            IDNA_NONTRANSITIONAL_TO_ASCII
        ));

        self::assertSame($this->rules, $this->rules->withUnicodeIDNAOption(
            $this->rules->getUnicodeIDNAOption()
        ));

        self::assertNotEquals($this->rules, $this->rules->withUnicodeIDNAOption(
            IDNA_NONTRANSITIONAL_TO_UNICODE
        ));
    }

    /**
     * @covers ::resolve
     * @covers ::resolveCookieDomain
     * @covers ::resolveICANNDomain
     * @covers ::resolvePrivateDomain
     * @covers ::validateDomain
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testNullWillReturnNullDomain(): void
    {
        $domain = $this->rules->resolve('COM');
        self::assertFalse($domain->getPublicSuffix()->isKnown());
    }


    /**
     * @covers ::resolve
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testThrowsTypeErrorOnWrongInput(): void
    {
        self::expectException(TypeError::class);
        $this->rules->resolve(date_create());
    }

    /**
     * @covers ::resolve
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\PublicSuffix::isKnown
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testIsSuffixValidFalse(): void
    {
        $domain = $this->rules->resolve('www.example.faketld');
        self::assertFalse($domain->getPublicSuffix()->isKnown());
    }

    /**
     * @covers ::resolve
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\PublicSuffix::isKnown
     * @covers \Pdp\PublicSuffix::isICANN
     * @covers \Pdp\PublicSuffix::isPrivate
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testIsSuffixValidTrue(): void
    {
        $domain = $this->rules->resolve('www.example.com');
        self::assertTrue($domain->getPublicSuffix()->isKnown());
        self::assertTrue($domain->getPublicSuffix()->isICANN());
        self::assertFalse($domain->getPublicSuffix()->isPrivate());
    }

    /**
     * @covers ::resolve
     * @covers ::resolveCookieDomain
     * @covers ::validateDomain
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\PublicSuffix::isKnown
     * @covers \Pdp\PublicSuffix::isICANN
     * @covers \Pdp\PublicSuffix::isPrivate
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testIsSuffixValidFalseWithPunycoded(): void
    {
        $domain = $this->rules->resolve('www.example.xn--85x722f');
        self::assertFalse($domain->getPublicSuffix()->isKnown());
        self::assertFalse($domain->getPublicSuffix()->isICANN());
        self::assertFalse($domain->getPublicSuffix()->isPrivate());
    }

    /**
     * @covers ::resolve
     * @covers ::resolveICANNDomain
     * @covers ::validateDomain
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\PublicSuffix::isKnown
     * @covers \Pdp\PublicSuffix::isICANN
     * @covers \Pdp\PublicSuffix::isPrivate
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testSubDomainIsNull(): void
    {
        $domain = $this->rules->resolve('ulb.ac.be');
        self::assertTrue($domain->getPublicSuffix()->isKnown());
        self::assertTrue($domain->getPublicSuffix()->isICANN());
        self::assertFalse($domain->getPublicSuffix()->isPrivate());
    }

    /**
     * @covers ::resolve
     * @covers ::resolveCookieDomain
     * @covers ::validateDomain
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testWithExceptionName(): void
    {
        $domain = $this->rules->resolve('_b%C3%A9bé.be-');
        self::assertNull($domain->getContent());
    }

    /**
     * @covers ::resolve
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testWithPrivateDomain(): void
    {
        $domain = $this->rules->resolve('thephpleague.github.io');
        self::assertTrue($domain->getPublicSuffix()->isKnown());
        self::assertFalse($domain->getPublicSuffix()->isICANN());
        self::assertTrue($domain->getPublicSuffix()->isPrivate());
        self::assertSame('github.io', $domain->getPublicSuffix()->getContent());
    }

    /**
     * @covers ::resolve
     */
    public function testWithAbsoluteHostInvalid(): void
    {
        $domain = $this->rules->resolve('private.ulb.ac.be.');
        self::assertSame('private.ulb.ac.be.', $domain->getContent());
        self::assertFalse($domain->getPublicSuffix()->isKnown());
        self::assertFalse($domain->getPublicSuffix()->isICANN());
        self::assertFalse($domain->getPublicSuffix()->isPrivate());
        self::assertNull($domain->getPublicSuffix()->getContent());
    }

    /**
     * @covers ::resolve
     * @covers ::resolvePrivateDomain
     * @covers ::validateDomain
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testWithPrivateDomainInvalid(): void
    {
        $domain = $this->rules->resolvePrivateDomain('private.ulb.ac.be');
        self::assertSame('private.ulb.ac.be', $domain->getContent());
        self::assertFalse($domain->getPublicSuffix()->isKnown());
        self::assertFalse($domain->getPublicSuffix()->isICANN());
        self::assertFalse($domain->getPublicSuffix()->isPrivate());
        self::assertSame('be', $domain->getPublicSuffix()->getContent());
    }

    /**
     * @covers ::resolve
     * @covers ::resolvePrivateDomain
     * @covers ::validateDomain
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testWithPrivateDomainValid(): void
    {
        $domain = $this->rules->resolvePrivateDomain('thephpleague.github.io');
        self::assertSame('thephpleague.github.io', $domain->getContent());
        self::assertTrue($domain->getPublicSuffix()->isKnown());
        self::assertFalse($domain->getPublicSuffix()->isICANN());
        self::assertTrue($domain->getPublicSuffix()->isPrivate());
        self::assertSame('github.io', $domain->getPublicSuffix()->getContent());
    }

    /**
     * @covers ::resolve
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testWithICANNDomainInvalid(): void
    {
        $domain = $this->rules->resolve('private.ulb.ac.be');
        self::assertSame('private.ulb.ac.be', $domain->getContent());
        self::assertTrue($domain->getPublicSuffix()->isKnown());
        self::assertTrue($domain->getPublicSuffix()->isICANN());
        self::assertFalse($domain->getPublicSuffix()->isPrivate());
        self::assertSame('ac.be', $domain->getPublicSuffix()->getContent());
    }

    /**
     * @covers ::resolve
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testWithDomainObject(): void
    {
        $domain = new ResolvedDomain(
            new Domain('private.ulb.ac.be'),
            PublicSuffix::fromICANNSection('ac.be')
        );

        $newDomain = $this->rules->resolve($domain);
        self::assertSame('private.ulb.ac.be', $domain->getContent());
        self::assertTrue($domain->getPublicSuffix()->isKnown());
        self::assertTrue($domain->getPublicSuffix()->isICANN());
        self::assertFalse($domain->getPublicSuffix()->isPrivate());
        self::assertSame('ac.be', $domain->getPublicSuffix()->getContent());
        self::assertEquals($domain, $newDomain);
    }

    /**
     * @covers ::getCookieEffectiveTLD
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testWithDomainInterfaceObject(): void
    {
        $domain = PublicSuffix::fromICANNSection('ulb.ac.be');

        self::assertSame(
            'ac.be',
            $this->rules->getCookieEffectiveTLD($domain)->getContent()
        );
    }

    /**
     * @covers ::resolve
     * @covers ::resolveICANNDomain
     * @covers ::validateDomain
     * @dataProvider parseDataProvider
     * @param ?string $publicSuffix
     * @param ?string $registrableDomain
     * @param ?string $expectedDomain
     */
    public function testGetRegistrableDomain(?string $publicSuffix, ?string $registrableDomain, string $domain, ?string $expectedDomain): void
    {
        $foundRegistrableDomain = $this->rules->resolve($domain)->getRegistrableDomain();

        self::assertSame($registrableDomain, $foundRegistrableDomain->getContent());
    }

    /**
     * @covers ::resolve
     * @covers ::resolveICANNDomain
     * @covers ::validateDomain
     * @covers \Pdp\DomainNameParser::parse
     * @dataProvider parseDataProvider
     * @param ?string $publicSuffix
     * @param ?string $registrableDomain
     * @param ?string $expectedDomain
     */
    public function testGetPublicSuffix(?string $publicSuffix, ?string $registrableDomain, string $domain, ?string $expectedDomain): void
    {
        $effectiveTLD = $this->rules->resolve($domain)->getPublicSuffix();

        self::assertSame($publicSuffix, $effectiveTLD->getContent());
    }

    /**
     * @covers ::resolve
     * @covers \Pdp\DomainNameParser::parse
     * @covers \Pdp\Domain::getContent
     * @dataProvider parseDataProvider
     *
     * @param ?string $publicSuffix
     * @param ?string $registrableDomain
     * @param ?string $expectedDomain
     */
    public function testGetDomain(?string $publicSuffix, ?string $registrableDomain, string $domain, ?string $expectedDomain): void
    {
        self::assertSame($expectedDomain, $this->rules->resolve($domain)->getContent());
    }

    public function parseDataProvider(): iterable
    {
        return [
            // public suffix, registrable domain, domain
            // BEGIN https://github.com/jeremykendall/php-domain-parser/issues/16
            'com tld' => [
                'publicSuffix' => 'com',
                'registrableDomain' => 'example.com',
                'domain' => 'us.example.com',
                'expectedDomain' => 'us.example.com',
            ],
            'na tld' => [
                'publicSuffix' => 'na',
                'registrableDomain' => 'example.na',
                'domain' => 'us.example.na',
                'expectedDomain' => 'us.example.na',
            ],
            'us.na tld' => [
                'publicSuffix' => 'us.na',
                'registrableDomain' => 'example.us.na',
                'domain' => 'www.example.us.na',
                'expectedDomain' => 'www.example.us.na',
            ],
            'org tld' => [
                'publicSuffix' => 'org',
                'registrableDomain' => 'example.org',
                'domain' => 'us.example.org',
                'expectedDomain' => 'us.example.org',
            ],
            'biz tld (1)' => [
                'publicSuffix' => 'biz',
                'registrableDomain' => 'broken.biz',
                'domain' => 'webhop.broken.biz',
                'expectedDomain' => 'webhop.broken.biz',
            ],
            'biz tld (2)' => [
                'publicSuffix' => 'webhop.biz',
                'registrableDomain' => 'broken.webhop.biz',
                'domain' =>  'www.broken.webhop.biz',
                'expectedDomain' => 'www.broken.webhop.biz',
            ],
            // END https://github.com/jeremykendall/php-domain-parser/issues/16
            // Test ipv6 URL
            'IP (1)' => [
                'publicSuffix' => null,
                'registrableDomain' => null,
                'domain' => '[::1]',
                'expectedDomain' => null, ],
            'IP (2)' => [
                'publicSuffix' => null,
                'registrableDomain' => null,
                'domain' => '[2001:db8:85a3:8d3:1319:8a2e:370:7348]',
                'expectedDomain' => null,
            ],
            'IP (3)' => [
                'publicSuffix' => null,
                'registrableDomain' => null,
                'domain' => '[2001:db8:85a3:8d3:1319:8a2e:370:7348]',
                'expectedDomain' => null,
            ],
            // Test IP address: Fixes #43
            'IP (4)' => [
                'publicSuffix' =>  null,
                'registrableDomain' =>  null,
                'domain' => '192.168.1.2',
                'expectedDomain' => null,
            ],
            // Link-local addresses and zone indices
            'IP (5)' => [
                'publicSuffix' =>  null,
                'registrableDomain' => null,
                'domain' => '[fe80::3%25eth0]',
                'expectedDomain' =>  null, ],
            'IP (6)' => [
                'publicSuffix' => null,
                'registrableDomain' => null,
                'domain' => '[fe80::1%2511]',
                'expectedDomain' => null,
            ],
            'fake tld' => [
                'publicSuffix' => 'faketld',
                'registrableDomain' => 'example.faketld',
                'domain' => 'example.faketld',
                'expectedDomain' => 'example.faketld',
            ],
            'fake tld with space' => [
                'publicSuffix' => null,
                'registrableDomain' =>  null,
                'domain' => 'fake.t ld',
                'expectedDomain' => null,
            ],
        ];
    }

    /**
     * @covers ::validateDomain
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testGetPublicSuffixThrowsCouldNotResolvePublicSuffix(): void
    {
        self::expectException(UnableToResolveDomain::class);

        $this->rules->getICANNEffectiveTLD('localhost');
    }

    /**
     * @covers ::getICANNEffectiveTLD
     * @covers \Pdp\DomainNameParser::parse
     *
     * @dataProvider invalidDomainParseProvider
     */
    public function testGetPublicSuffixThrowsInvalidDomainException(string $domain): void
    {
        self::expectException(InvalidDomainName::class);

        $this->rules->getICANNEffectiveTLD($domain);
    }

    public function invalidDomainParseProvider(): iterable
    {
        return [
            'IPv6' => ['[::1]'],
            'IPv4' => ['192.168.1.2'],
            'invalid host: label too long' => [implode('', array_fill(0, 64, 'a')).'.com'],
        ];
    }

    /**
     * @covers ::getICANNEffectiveTLD
     * @covers \Pdp\DomainNameParser::parse
     *
     * @dataProvider invalidHostParseProvider
     */
    public function testGetPublicSuffixThrowsInvalidHostException(string $domain): void
    {
        self::expectException(InvalidHost::class);

        $this->rules->getICANNEffectiveTLD($domain);
    }

    public function invalidHostParseProvider(): iterable
    {
        $long_label = implode('.', array_fill(0, 62, 'a'));

        return [
            'multiple label with URI delimiter' => ['local.ho/st'],
            'invalid host: invalid label according to RFC3986' => ['www.fußball.com-'],
            'invalid host: host contains space' => ['re view.com'],
            'invalid host: host too long' => ["$long_label.$long_label.$long_label. $long_label.$long_label"],
        ];
    }


    /**
     * @covers ::getCookieEffectiveTLD
     * @covers \Pdp\DomainNameParser::parse
     * @dataProvider validPublicSectionProvider
     * @param ?string $domain
     * @param ?string $expected
     */
    public function testPublicSuffixSection(?string $domain, ?string $expected): void
    {
        $publicSuffix =  $this->rules->getCookieEffectiveTLD($domain);
        self::assertSame($expected, $publicSuffix->getContent());
    }

    public function validPublicSectionProvider(): iterable
    {
        return [
            'idn domain' => [
                'domain' => 'Яндекс.РФ',
                'expected' => 'рф',
            ],
            'ascii domain' => [
                'domain' => 'ulb.ac.be',
                'expected' => 'ac.be',
            ],
            'unknown tld' => [
                'domain' => 'yours.truly.faketld',
                'expected' => 'faketld',
            ],
        ];
    }

    /**
     * This is my version of the checkPublicSuffix function referred to in the
     * test instructions at the Public Suffix List project.
     *
     * "You will need to define a checkPublicSuffix() function which takes as a
     * parameter a domain name and the public suffix, runs your implementation
     * on the domain name and checks the result is the public suffix expected."
     *
     * @see http://publicsuffix.org/list/
     *
     * @param string|null $input    Domain and public suffix
     * @param string|null $expected Expected result
     */
    public function checkPublicSuffix(?string $input, ?string $expected): void
    {
        self::assertSame($expected, $this->rules->resolve($input)->getRegistrableDomain()->getContent());
    }

    /**
     * This test case is based on the test data linked at
     * http://publicsuffix.org/list/ and provided by Rob Strading of Comodo.
     *
     * @see
     * http://mxr.mozilla.org/mozilla-central/source/netwerk/test/unit/data/test_psl.txt?raw=1
     *
     * @covers ::resolve
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\DomainNameParser::parse
     */
    public function testPublicSuffixSpec(): void
    {
        // Test data from Rob Stradling at Comodo
        // http://mxr.mozilla.org/mozilla-central/source/netwerk/test/unit/data/test_psl.txt?raw=1
        // Any copyright is dedicated to the Public Domain.
        // http://creativecommons.org/publicdomain/zero/1.0/

        // null input.
        $this->checkPublicSuffix(null, null);
        // Mixed case.
        $this->checkPublicSuffix('COM', null);
        $this->checkPublicSuffix('example.COM', 'example.com');
        $this->checkPublicSuffix('WwW.example.COM', 'example.com');
        // Leading dot.
        $this->checkPublicSuffix('.com', null);
        $this->checkPublicSuffix('.example', null);
        $this->checkPublicSuffix('.example.com', null);
        $this->checkPublicSuffix('.example.example', null);
        // Unlisted TLD.
        // Addresses algorithm rule #2: If no rules match, the prevailing rule is "*".
        $this->checkPublicSuffix('example', null);
        $this->checkPublicSuffix('example.example', 'example.example');
        $this->checkPublicSuffix('b.example.example', 'example.example');
        $this->checkPublicSuffix('a.b.example.example', 'example.example');
        // TLD with only 1 rule.
        $this->checkPublicSuffix('biz', null);
        $this->checkPublicSuffix('domain.biz', 'domain.biz');
        $this->checkPublicSuffix('b.domain.biz', 'domain.biz');
        $this->checkPublicSuffix('a.b.domain.biz', 'domain.biz');
        // TLD with some 2-level rules.
        $this->checkPublicSuffix('com', null);
        $this->checkPublicSuffix('example.com', 'example.com');
        $this->checkPublicSuffix('b.example.com', 'example.com');
        $this->checkPublicSuffix('a.b.example.com', 'example.com');

        /* REPLACE uk.com by ac.be because uk.com is a PRIVATE DOMAIN */
        $this->checkPublicSuffix('ac.be', null);
        $this->checkPublicSuffix('ulb.ac.be', 'ulb.ac.be');
        $this->checkPublicSuffix('b.ulb.ac.be', 'ulb.ac.be');
        $this->checkPublicSuffix('a.b.ulb.ac.be', 'ulb.ac.be');

        $this->checkPublicSuffix('test.ac', 'test.ac');
        // TLD with only 1 (wildcard) rule.
        $this->checkPublicSuffix('mm', null);
        $this->checkPublicSuffix('c.mm', null);
        $this->checkPublicSuffix('b.c.mm', 'b.c.mm');
        $this->checkPublicSuffix('a.b.c.mm', 'b.c.mm');
        // More complex TLD.
        $this->checkPublicSuffix('jp', null);
        $this->checkPublicSuffix('test.jp', 'test.jp');
        $this->checkPublicSuffix('www.test.jp', 'test.jp');
        $this->checkPublicSuffix('ac.jp', null);
        $this->checkPublicSuffix('test.ac.jp', 'test.ac.jp');
        $this->checkPublicSuffix('www.test.ac.jp', 'test.ac.jp');
        $this->checkPublicSuffix('kyoto.jp', null);
        $this->checkPublicSuffix('test.kyoto.jp', 'test.kyoto.jp');
        $this->checkPublicSuffix('ide.kyoto.jp', null);
        $this->checkPublicSuffix('b.ide.kyoto.jp', 'b.ide.kyoto.jp');
        $this->checkPublicSuffix('a.b.ide.kyoto.jp', 'b.ide.kyoto.jp');
        $this->checkPublicSuffix('c.kobe.jp', null);
        $this->checkPublicSuffix('b.c.kobe.jp', 'b.c.kobe.jp');
        $this->checkPublicSuffix('a.b.c.kobe.jp', 'b.c.kobe.jp');
        $this->checkPublicSuffix('city.kobe.jp', 'city.kobe.jp');
        $this->checkPublicSuffix('www.city.kobe.jp', 'city.kobe.jp');
        // TLD with a wildcard rule and exceptions.
        $this->checkPublicSuffix('ck', null);
        $this->checkPublicSuffix('test.ck', null);
        $this->checkPublicSuffix('b.test.ck', 'b.test.ck');
        $this->checkPublicSuffix('a.b.test.ck', 'b.test.ck');
        $this->checkPublicSuffix('www.ck', 'www.ck');
        $this->checkPublicSuffix('www.www.ck', 'www.ck');
        // US K12.
        $this->checkPublicSuffix('us', null);
        $this->checkPublicSuffix('test.us', 'test.us');
        $this->checkPublicSuffix('www.test.us', 'test.us');
        $this->checkPublicSuffix('ak.us', null);
        $this->checkPublicSuffix('test.ak.us', 'test.ak.us');
        $this->checkPublicSuffix('www.test.ak.us', 'test.ak.us');
        $this->checkPublicSuffix('k12.ak.us', null);
        $this->checkPublicSuffix('test.k12.ak.us', 'test.k12.ak.us');
        $this->checkPublicSuffix('www.test.k12.ak.us', 'test.k12.ak.us');
        // IDN labels.
        $this->checkPublicSuffix('食狮.com.cn', '食狮.com.cn');
        $this->checkPublicSuffix('食狮.公司.cn', '食狮.公司.cn');
        $this->checkPublicSuffix('www.食狮.公司.cn', '食狮.公司.cn');
        $this->checkPublicSuffix('shishi.公司.cn', 'shishi.公司.cn');
        $this->checkPublicSuffix('公司.cn', null);
        $this->checkPublicSuffix('食狮.中国', '食狮.中国');
        $this->checkPublicSuffix('www.食狮.中国', '食狮.中国');
        $this->checkPublicSuffix('shishi.中国', 'shishi.中国');
        $this->checkPublicSuffix('中国', null);
        $this->checkPublicSuffix('www.faß.de', 'fass.de');
        // Same as above, but punycoded.
        $this->checkPublicSuffix('xn--85x722f.com.cn', 'xn--85x722f.com.cn');
        $this->checkPublicSuffix('xn--85x722f.xn--55qx5d.cn', 'xn--85x722f.xn--55qx5d.cn');
        $this->checkPublicSuffix('www.xn--85x722f.xn--55qx5d.cn', 'xn--85x722f.xn--55qx5d.cn');
        $this->checkPublicSuffix('shishi.xn--55qx5d.cn', 'shishi.xn--55qx5d.cn');
        $this->checkPublicSuffix('xn--55qx5d.cn', null);
        $this->checkPublicSuffix('xn--85x722f.xn--fiqs8s', 'xn--85x722f.xn--fiqs8s');
        $this->checkPublicSuffix('www.xn--85x722f.xn--fiqs8s', 'xn--85x722f.xn--fiqs8s');
        $this->checkPublicSuffix('shishi.xn--fiqs8s', 'shishi.xn--fiqs8s');
        $this->checkPublicSuffix('xn--fiqs8s', null);
    }

    /**
     * @covers ::getAsciiIDNAOption
     * @covers ::getUnicodeIDNAOption
     */
    public function testResolveWithIDNAOptions(): void
    {
        $resolvedByDefault = $this->rules->resolve('foo.de');
        self::assertSame(
            [IDNA_DEFAULT, IDNA_DEFAULT],
            [$resolvedByDefault->getAsciiIDNAOption(), $resolvedByDefault->getUnicodeIDNAOption()]
        );

        /** @var string $string */
        $string = file_get_contents(__DIR__.'/data/public_suffix_list.dat');
        $rules = Rules::fromString($string, IDNA_NONTRANSITIONAL_TO_ASCII, IDNA_NONTRANSITIONAL_TO_UNICODE);
        $resolved = $rules->resolve('foo.de');

        self::assertSame(
            [$rules->getAsciiIDNAOption(), $rules->getUnicodeIDNAOption()],
            [$resolved->getAsciiIDNAOption(), $resolved->getUnicodeIDNAOption()]
        );
    }

    /**
     * @covers ::getCookieEffectiveTLD
     * @covers ::getICANNEffectiveTLD
     * @covers ::getPrivateEffectiveTLD
     * @dataProvider effectiveTLDProvider
     */
    public function testEffectiveTLDResolution(string $host, string $cookieETLD, string $icannETLD, string $privateETLD): void
    {
        self::assertSame($cookieETLD, (string) $this->rules->getCookieEffectiveTLD($host));
        self::assertSame($icannETLD, (string) $this->rules->getICANNEffectiveTLD($host));
        self::assertSame($privateETLD, (string) $this->rules->getPrivateEffectiveTLD($host));
    }

    public function effectiveTLDProvider(): iterable
    {
        return [
            'simple TLD' => [
                'host' => 'www.example.com',
                'cookieETLD' => 'com',
                'icannETLD' => 'com',
                'privateETLD' => 'com',
            ],
            'complex ICANN TLD' => [
                'host' => 'www.ulb.ac.be',
                'cookieETLD' => 'ac.be',
                'icannETLD' => 'ac.be',
                'privateETLD' => 'be',
            ],
            'private domain effective TLD' => [
                'host' => 'myblog.blogspot.com',
                'cookieETLD' => 'blogspot.com',
                'icannETLD' => 'com',
                'privateETLD' => 'blogspot.com',
            ],
        ];
    }
}
