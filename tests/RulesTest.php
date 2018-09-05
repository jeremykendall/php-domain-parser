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

use Pdp\Cache;
use Pdp\CurlHttpClient;
use Pdp\Domain;
use Pdp\Exception\CouldNotLoadRules;
use Pdp\Exception\CouldNotResolvePublicSuffix;
use Pdp\Exception\InvalidDomain;
use Pdp\Manager;
use Pdp\PublicSuffix;
use Pdp\Rules;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @coversDefaultClass Pdp\Rules
 */
class RulesTest extends TestCase
{
    /**
     * @var Rules
     */
    private $rules;

    public function setUp()
    {
        $this->rules = (new Manager(new Cache(), new CurlHttpClient()))->getRules();
    }

    /**
     * @covers ::createFromPath
     * @covers ::createFromString
     * @covers ::__construct
     */
    public function testCreateFromPath()
    {
        $context = stream_context_create([
            'http'=> [
                'method' => 'GET',
                'header' => "Accept-language: en\r\nCookie: foo=bar\r\n",
            ],
        ]);

        $rules = Rules::createFromPath(__DIR__.'/data/public_suffix_list.dat', $context);
        $this->assertInstanceOf(Rules::class, $rules);
    }

    /**
     * @covers ::createFromPath
     */
    public function testCreateFromPathThrowsException()
    {
        $this->expectException(CouldNotLoadRules::class);
        Rules::createFromPath('/foo/bar.dat');
    }

    /**
     * @covers ::__set_state
     * @covers ::__construct
     */
    public function testDomainInternalPhpMethod()
    {
        $generateRules = eval('return '.var_export($this->rules, true).';');
        $this->assertEquals($this->rules, $generateRules);
    }

    /**
     * @covers ::resolve
     * @covers ::validateSection
     * @covers \Pdp\Domain::isResolvable
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\PublicSuffix::isKnown
     * @covers \Pdp\Domain::isKnown
     * @covers \Pdp\IDNAConverterTrait::setLabels
     */
    public function testNullWillReturnNullDomain()
    {
        $domain = $this->rules->resolve('COM');
        $this->assertFalse($domain->isKnown());
    }


    /**
     * @covers ::resolve
     * @covers \Pdp\IDNAConverterTrait::setLabels
     */
    public function testThrowsTypeErrorOnWrongInput()
    {
        $this->expectException(TypeError::class);
        $this->rules->resolve(date_create());
    }

    /**
     * @covers ::resolve
     * @covers ::validateSection
     */
    public function testResolveThrowsExceptionOnWrongDomainType()
    {
        $this->expectException(CouldNotResolvePublicSuffix::class);
        $this->rules->resolve('www.example.com', 'foobar');
    }

    /**
     * @covers ::resolve
     * @covers ::validateSection
     * @covers \Pdp\Domain::isResolvable
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\PublicSuffix::isKnown
     * @covers \Pdp\Domain::isKnown
     * @covers \Pdp\IDNAConverterTrait::setLabels
     */
    public function testIsSuffixValidFalse()
    {
        $domain = $this->rules->resolve('www.example.faketld');
        $this->assertFalse($domain->isKnown());
    }

    /**
     * @covers ::resolve
     * @covers ::validateSection
     * @covers \Pdp\Domain::isResolvable
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\PublicSuffix::isKnown
     * @covers \Pdp\PublicSuffix::isICANN
     * @covers \Pdp\PublicSuffix::isPrivate
     * @covers \Pdp\Domain::withPublicSuffix
     * @covers \Pdp\Domain::isKnown
     * @covers \Pdp\Domain::isICANN
     * @covers \Pdp\Domain::isPrivate
     * @covers \Pdp\IDNAConverterTrait::setLabels
     */
    public function testIsSuffixValidTrue()
    {
        $domain = $this->rules->resolve('www.example.com', Rules::ICANN_DOMAINS);
        $this->assertTrue($domain->isKnown());
        $this->assertTrue($domain->isICANN());
        $this->assertFalse($domain->isPrivate());
    }

    /**
     * @covers ::resolve
     * @covers ::validateSection
     * @covers \Pdp\Domain::isResolvable
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\PublicSuffix::isKnown
     * @covers \Pdp\PublicSuffix::isICANN
     * @covers \Pdp\PublicSuffix::isPrivate
     * @covers \Pdp\Domain::withPublicSuffix
     * @covers \Pdp\Domain::isKnown
     * @covers \Pdp\Domain::isICANN
     * @covers \Pdp\Domain::isPrivate
     * @covers \Pdp\IDNAConverterTrait::setLabels
     */
    public function testIsSuffixValidFalseWithPunycoded()
    {
        $domain = $this->rules->resolve('www.example.xn--85x722f');
        $this->assertFalse($domain->isKnown());
        $this->assertFalse($domain->isICANN());
        $this->assertFalse($domain->isPrivate());
    }

    /**
     * @covers ::resolve
     * @covers ::validateSection
     * @covers \Pdp\Domain::isResolvable
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\PublicSuffix::isKnown
     * @covers \Pdp\PublicSuffix::isICANN
     * @covers \Pdp\PublicSuffix::isPrivate
     * @covers \Pdp\Domain::withPublicSuffix
     * @covers \Pdp\Domain::isKnown
     * @covers \Pdp\Domain::isICANN
     * @covers \Pdp\Domain::isPrivate
     * @covers \Pdp\IDNAConverterTrait::setLabels
     */
    public function testSubDomainIsNull()
    {
        $domain = $this->rules->resolve('ulb.ac.be', Rules::ICANN_DOMAINS);
        $this->assertTrue($domain->isKnown());
        $this->assertTrue($domain->isICANN());
        $this->assertFalse($domain->isPrivate());
    }

    /**
     * @covers ::resolve
     * @covers ::validateSection
     * @covers \Pdp\IDNAConverterTrait::setLabels
     */
    public function testWithExceptionName()
    {
        $domain = $this->rules->resolve('_b%C3%A9bé.be-');
        $this->assertNull($domain->getContent());
    }

    /**
     * @covers ::resolve
     * @covers ::validateSection
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\IDNAConverterTrait::setLabels
     */
    public function testWithPrivateDomain()
    {
        $domain = $this->rules->resolve('thephpleague.github.io');
        $this->assertTrue($domain->isKnown());
        $this->assertFalse($domain->isICANN());
        $this->assertTrue($domain->isPrivate());
        $this->assertSame('github.io', $domain->getPublicSuffix());
    }

    /**
     * @covers ::resolve
     * @covers \Pdp\Domain::isResolvable
     */
    public function testWithAbsoluteHostInvalid()
    {
        $domain = $this->rules->resolve('private.ulb.ac.be.');
        $this->assertSame('private.ulb.ac.be.', $domain->getContent());
        $this->assertFalse($domain->isKnown());
        $this->assertFalse($domain->isICANN());
        $this->assertFalse($domain->isPrivate());
        $this->assertNull($domain->getPublicSuffix());
    }

    /**
     * @covers ::resolve
     * @covers ::validateSection
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\IDNAConverterTrait::setLabels
     */
    public function testWithPrivateDomainInvalid()
    {
        $domain = $this->rules->resolve('private.ulb.ac.be', Rules::PRIVATE_DOMAINS);
        $this->assertSame('private.ulb.ac.be', $domain->getContent());
        $this->assertFalse($domain->isKnown());
        $this->assertFalse($domain->isICANN());
        $this->assertFalse($domain->isPrivate());
        $this->assertSame('be', $domain->getPublicSuffix());
    }

    /**
     * @covers ::resolve
     * @covers ::validateSection
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\IDNAConverterTrait::setLabels
     */
    public function testWithPrivateDomainValid()
    {
        $domain = $this->rules->resolve('thephpleague.github.io', Rules::PRIVATE_DOMAINS);
        $this->assertSame('thephpleague.github.io', $domain->getContent());
        $this->assertTrue($domain->isKnown());
        $this->assertFalse($domain->isICANN());
        $this->assertTrue($domain->isPrivate());
        $this->assertSame('github.io', $domain->getPublicSuffix());
    }

    /**
     * @covers ::resolve
     * @covers ::validateSection
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\IDNAConverterTrait::setLabels
     */
    public function testWithICANNDomainInvalid()
    {
        $domain = $this->rules->resolve('private.ulb.ac.be');
        $this->assertSame('private.ulb.ac.be', $domain->getContent());
        $this->assertTrue($domain->isKnown());
        $this->assertTrue($domain->isICANN());
        $this->assertFalse($domain->isPrivate());
        $this->assertSame('ac.be', $domain->getPublicSuffix());
    }

    /**
     * @covers ::resolve
     * @covers ::validateSection
     * @covers ::findPublicSuffix
     * @covers ::findPublicSuffixFromSection
     * @covers \Pdp\PublicSuffix::setSection
     * @covers \Pdp\IDNAConverterTrait::setLabels
     */
    public function testWithDomainObject()
    {
        $domain = new Domain('private.ulb.ac.be', new PublicSuffix('ac.be', Rules::ICANN_DOMAINS));
        $newDomain = $this->rules->resolve($domain);
        $this->assertSame('private.ulb.ac.be', $domain->getContent());
        $this->assertTrue($domain->isKnown());
        $this->assertTrue($domain->isICANN());
        $this->assertFalse($domain->isPrivate());
        $this->assertSame('ac.be', $domain->getPublicSuffix());
        $this->assertSame($domain, $newDomain);
    }

    /**
     * @covers ::getPublicSuffix
     * @covers \Pdp\IDNAConverterTrait::setLabels
     */
    public function testWithDomainInterfaceObject()
    {
        $this->assertSame(
            'ac.be',
            $this->rules->getPublicSuffix(new PublicSuffix('ul.ac.be', Rules::ICANN_DOMAINS))->getContent()
        );
    }

    /**
     * @covers ::resolve
     * @covers \Pdp\Domain::setRegistrableDomain
     * @covers \Pdp\Domain::getRegistrableDomain
     * @dataProvider parseDataProvider
     *
     * @param mixed $publicSuffix
     * @param mixed $registrableDomain
     * @param mixed $domain
     * @param mixed $expectedDomain
     */
    public function testGetRegistrableDomain($publicSuffix, $registrableDomain, $domain, $expectedDomain)
    {
        $this->assertSame($registrableDomain, $this->rules->resolve($domain, Rules::ICANN_DOMAINS)->getRegistrableDomain());
    }

    /**
     * @covers ::resolve
     * @covers \Pdp\IDNAConverterTrait::setLabels
     * @covers \Pdp\Domain::setPublicSuffix
     * @covers \Pdp\Domain::getPublicSuffix
     * @dataProvider parseDataProvider
     *
     * @param mixed $publicSuffix
     * @param mixed $registrableDomain
     * @param mixed $domain
     * @param mixed $expectedDomain
     */
    public function testGetPublicSuffix($publicSuffix, $registrableDomain, $domain, $expectedDomain)
    {
        $this->assertSame($publicSuffix, $this->rules->resolve($domain, Rules::ICANN_DOMAINS)->getPublicSuffix());
    }

    /**
     * @covers ::resolve
     * @covers \Pdp\IDNAConverterTrait::setLabels
     * @covers \Pdp\Domain::withPublicSuffix
     * @covers \Pdp\Domain::getContent
     * @dataProvider parseDataProvider
     *
     * @param mixed $publicSuffix
     * @param mixed $registrableDomain
     * @param mixed $domain
     * @param mixed $expectedDomain
     */
    public function testGetDomain($publicSuffix, $registrableDomain, $domain, $expectedDomain)
    {
        $this->assertSame($expectedDomain, $this->rules->resolve($domain, Rules::ICANN_DOMAINS)->getContent());
    }

    public function parseDataProvider()
    {
        return [
            // public suffix, registrable domain, domain
            // BEGIN https://github.com/jeremykendall/php-domain-parser/issues/16
            'com tld' => ['com', 'example.com', 'us.example.com', 'us.example.com'],
            'na tld' => ['na', 'example.na', 'us.example.na', 'us.example.na'],
            'us.na tld' => ['us.na', 'example.us.na', 'www.example.us.na', 'www.example.us.na'],
            'org tld' => ['org', 'example.org', 'us.example.org', 'us.example.org'],
            'biz tld (1)' => ['biz', 'broken.biz', 'webhop.broken.biz', 'webhop.broken.biz'],
            'biz tld (2)' => ['biz', 'webhop.biz', 'www.broken.webhop.biz', 'www.broken.webhop.biz'],
            // END https://github.com/jeremykendall/php-domain-parser/issues/16
            // Test ipv6 URL
            'IP (1)' => [null, null, '[::1]', null],
            'IP (2)' => [null, null, '[2001:db8:85a3:8d3:1319:8a2e:370:7348]', null],
            'IP (3)' => [null, null, '[2001:db8:85a3:8d3:1319:8a2e:370:7348]', null],
            // Test IP address: Fixes #43
            'IP (4)' => [null, null, '192.168.1.2', null],
            // Link-local addresses and zone indices
            'IP (5)' => [null, null, '[fe80::3%25eth0]', null],
            'IP (6)' => [null, null, '[fe80::1%2511]', null],
            'fake tld' => ['faketld', 'example.faketld', 'example.faketld', 'example.faketld'],
            'fake tld with space' => [null, null, 'fake.t ld', null],
        ];
    }

    /**
     * @covers ::getPublicSuffix
     * @covers ::validateSection
     * @covers \Pdp\Domain::isResolvable
     * @covers \Pdp\IDNAConverterTrait::setLabels
     * @dataProvider invalidParseProvider
     *
     * @param mixed $domain
     * @param mixed $section
     */
    public function testGetPublicSuffixThrowsCouldNotResolvePublicSuffix($domain, $section)
    {
        $this->expectException(CouldNotResolvePublicSuffix::class);
        $this->rules->getPublicSuffix($domain, $section);
    }

    public function invalidParseProvider()
    {
        $long_label = implode('.', array_fill(0, 62, 'a'));

        return [
            'single label host' => ['localhost', Rules::ICANN_DOMAINS],
        ];
    }

    /**
     * @covers ::getPublicSuffix
     * @covers ::validateSection
     * @covers \Pdp\Domain::isResolvable
     * @covers \Pdp\IDNAConverterTrait::setLabels
     * @dataProvider invalidDomainParseProvider
     *
     * @param mixed $domain
     * @param mixed $section
     */
    public function testGetPublicSuffixThrowsInvalidDomainException($domain, $section)
    {
        $this->expectException(InvalidDomain::class);
        $this->rules->getPublicSuffix($domain, $section);
    }

    public function invalidDomainParseProvider()
    {
        $long_label = implode('.', array_fill(0, 62, 'a'));

        return [
            'IPv6' => ['[::1]', Rules::ICANN_DOMAINS],
            'IPv4' => ['192.168.1.2', Rules::ICANN_DOMAINS],
            'multiple label with URI delimiter' => ['local.ho/st', Rules::ICANN_DOMAINS],
            'invalid host: label too long' => [implode('', array_fill(0, 64, 'a')).'.com', Rules::ICANN_DOMAINS],
            'invalid host: host too long' => ["$long_label.$long_label.$long_label. $long_label.$long_label", Rules::ICANN_DOMAINS],
            'invalid host: invalid label according to RFC3986' => ['www.fußball.com-', Rules::ICANN_DOMAINS],
            'invalid host: host contains space' => ['re view.com', Rules::ICANN_DOMAINS],
        ];
    }


    /**
     * @covers ::getPublicSuffix
     * @covers ::validateSection
     * @covers \Pdp\Domain::isResolvable
     * @covers \Pdp\IDNAConverterTrait::setLabels
     * @dataProvider validPublicSectionProvider
     *
     * @param string|null $domain
     * @param string|null $expected
     */
    public function testPublicSuffixSection($domain, $expected)
    {
        $publicSuffix =  $this->rules->getPublicSuffix($domain);
        $this->assertSame($expected, $publicSuffix->getContent());
    }

    public function validPublicSectionProvider()
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
    public function checkPublicSuffix($input, $expected)
    {
        $this->assertSame($expected, $this->rules->resolve($input)->getRegistrableDomain());
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
     * @covers \Pdp\Domain::withPublicSuffix
     * @covers \Pdp\Domain::getRegistrableDomain
     * @covers \Pdp\IDNAConverterTrait::setLabels
     */
    public function testPublicSuffixSpec()
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
}
