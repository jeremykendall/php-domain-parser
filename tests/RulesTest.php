<?php

declare(strict_types=1);

namespace pdp\tests;

use Pdp\Cache;
use Pdp\CurlHttpClient;
use Pdp\Domain;
use Pdp\Exception;
use Pdp\Manager;
use Pdp\Rules;
use PHPUnit\Framework\TestCase;

class RulesTest extends TestCase
{
    /**
     * @var Rules
     */
    private $rules;

    public function setUp()
    {
        $manager = new Manager(new Cache(), new CurlHttpClient());
        $this->rules = $manager->getRules();
    }

    public function testCreateFromPath()
    {
        $rules = Rules::createFromPath(__DIR__.'/data/public_suffix_list.dat');
        $this->assertInstanceOf(Rules::class, $rules);
    }

    public function testCreateFromPathThrowsException()
    {
        $this->expectException(Exception::class);
        Rules::createFromPath('/foo/bar.dat');
    }

    public function testNullWillReturnNullDomain()
    {
        $domain = $this->rules->resolve('COM');
        $this->assertFalse($domain->isKnown());
    }

    public function testResolveThrowsExceptionOnWrongDomainType()
    {
        $this->expectException(Exception::class);
        $this->rules->resolve('www.example.com', 'foobar');
    }

    public function testIsSuffixValidFalse()
    {
        $domain = $this->rules->resolve('www.example.faketld');
        $this->assertFalse($domain->isKnown());
        $this->assertSame('www', $domain->getSubDomain());
    }

    public function testIsSuffixValidTrue()
    {
        $domain = $this->rules->resolve('www.example.com', Rules::ICANN_DOMAINS);
        $this->assertTrue($domain->isKnown());
        $this->assertTrue($domain->isICANN());
        $this->assertFalse($domain->isPrivate());
        $this->assertSame('www', $domain->getSubDomain());
    }

    public function testIsSuffixValidFalseWithPunycoded()
    {
        $domain = $this->rules->resolve('www.example.xn--85x722f');
        $this->assertFalse($domain->isKnown());
        $this->assertFalse($domain->isICANN());
        $this->assertFalse($domain->isPrivate());
        $this->assertSame('xn--85x722f', $domain->getPublicSuffix());
        $this->assertSame('www', $domain->getSubDomain());
    }

    public function testSudDomainIsNull()
    {
        $domain = $this->rules->resolve('ulb.ac.be', Rules::ICANN_DOMAINS);
        $this->assertTrue($domain->isKnown());
        $this->assertTrue($domain->isICANN());
        $this->assertFalse($domain->isPrivate());
        $this->assertSame('ac.be', $domain->getPublicSuffix());
        $this->assertSame('ulb.ac.be', $domain->getRegistrableDomain());
        $this->assertNull($domain->getSubDomain());
    }

    public function testWithInvalidDomainName()
    {
        $domain = $this->rules->resolve('_b%C3%A9bé.be-');
        $this->assertSame('_b%C3%A9bé.be-', $domain->getDomain());
        $this->assertFalse($domain->isKnown());
        $this->assertFalse($domain->isICANN());
        $this->assertFalse($domain->isPrivate());
        $this->assertNull($domain->getPublicSuffix());
        $this->assertNull($domain->getRegistrableDomain());
    }

    public function testWithPrivateDomain()
    {
        $domain = $this->rules->resolve('thephpleague.github.io');
        $this->assertSame('thephpleague.github.io', $domain->getDomain());
        $this->assertTrue($domain->isKnown());
        $this->assertFalse($domain->isICANN());
        $this->assertTrue($domain->isPrivate());
        $this->assertSame('github.io', $domain->getPublicSuffix());
        $this->assertSame('thephpleague.github.io', $domain->getRegistrableDomain());
        $this->assertNull($domain->getSubDomain());
    }

    public function testWithPrivateDomainInvalid()
    {
        $domain = $this->rules->resolve('private.ulb.ac.be', Rules::PRIVATE_DOMAINS);
        $this->assertSame('private.ulb.ac.be', $domain->getDomain());
        $this->assertFalse($domain->isKnown());
        $this->assertFalse($domain->isICANN());
        $this->assertFalse($domain->isPrivate());
        $this->assertSame('be', $domain->getPublicSuffix());
        $this->assertSame('ac.be', $domain->getRegistrableDomain());
        $this->assertSame('private.ulb', $domain->getSubDomain());
    }

    public function testWithPrivateDomainValid()
    {
        $domain = $this->rules->resolve('thephpleague.github.io', Rules::PRIVATE_DOMAINS);
        $this->assertSame('thephpleague.github.io', $domain->getDomain());
        $this->assertTrue($domain->isKnown());
        $this->assertFalse($domain->isICANN());
        $this->assertTrue($domain->isPrivate());
        $this->assertSame('github.io', $domain->getPublicSuffix());
        $this->assertSame('thephpleague.github.io', $domain->getRegistrableDomain());
        $this->assertNull($domain->getSubDomain());
    }

    public function testWithICANNDomainInvalid()
    {
        $domain = $this->rules->resolve('private.ulb.ac.be');
        $this->assertSame('private.ulb.ac.be', $domain->getDomain());
        $this->assertTrue($domain->isKnown());
        $this->assertTrue($domain->isICANN());
        $this->assertFalse($domain->isPrivate());
        $this->assertSame('ac.be', $domain->getPublicSuffix());
        $this->assertSame('ulb.ac.be', $domain->getRegistrableDomain());
        $this->assertSame('private', $domain->getSubDomain());
    }

    /**
     * @dataProvider parseDataProvider
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
     * @dataProvider parseDataProvider
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
     * @dataProvider parseDataProvider
     * @param mixed $publicSuffix
     * @param mixed $registrableDomain
     * @param mixed $domain
     * @param mixed $expectedDomain
     */
    public function testGetDomain($publicSuffix, $registrableDomain, $domain, $expectedDomain)
    {
        $this->assertSame($expectedDomain, $this->rules->resolve($domain, Rules::ICANN_DOMAINS)->getDomain());
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
        ];
    }

    public function testGetPublicSuffixHandlesWrongCaseProperly()
    {
        $publicSuffix = 'рф';
        $domain = 'Яндекс.РФ';

        $this->assertSame($publicSuffix, $this->rules->resolve($domain, Rules::ICANN_DOMAINS)->getPublicSuffix());
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
     * @param string $input    Domain and public suffix
     * @param string $expected Expected result
     */
    public function checkPublicSuffix($input, $expected)
    {
        $this->assertSame($expected, $this->rules->resolve($input, Rules::ICANN_DOMAINS)->getRegistrableDomain());
    }

    /**
     * This test case is based on the test data linked at
     * http://publicsuffix.org/list/ and provided by Rob Strading of Comodo.
     *
     * @see
     * http://mxr.mozilla.org/mozilla-central/source/netwerk/test/unit/data/test_psl.txt?raw=1
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
