<?php

declare(strict_types=1);

/**
 * Public Suffix List PHP: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/publicsuffixlist-php for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 * @license   http://github.com/jeremykendall/publicsuffixlist-php/blob/master/LICENSE MIT License
 */
namespace Pdp;

use Pdp\PublicSuffixListManager;
use PHPUnit\Framework\TestCase;

class PublicSuffixListTest extends TestCase
{
    /**
     * @var PublicSuffixList
     */
    private $list;

    private $dataDir;

    protected function setUp()
    {
        parent::setUp();
        $this->list = new PublicSuffixList();
        $this->dataDir = realpath(dirname(__DIR__) . '/../../data');
    }

    public function testConstructorWithFilePath()
    {
        $this->assertEquals($this->list, new PublicSuffixList($this->dataDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE));
    }

    public function testNullWillReturnNullDomain()
    {
        $domain = $this->list->query('COM');
        $this->assertFalse($domain->isValid());
        $this->assertInstanceOf(NullDomain::class, $domain);
    }

    public function testIsSuffixValidFalse()
    {
        $domain = $this->list->query('www.example.faketld');
        $this->assertFalse($domain->isValid());
        $this->assertInstanceOf(UnmatchedDomain::class, $domain);
    }

    public function testIsSuffixValidTrue()
    {
        $domain = $this->list->query('www.example.com');
        $this->assertTrue($domain->isValid());
        $this->assertInstanceOf(MatchedDomain::class, $domain);
    }

    public function testIsSuffixValidFalseWithPunycoded()
    {
        $domain = $this->list->query('www.example.xn--85x722f');
        $this->assertFalse($domain->isValid());
        $this->assertInstanceOf(UnmatchedDomain::class, $domain);
        $this->assertSame('xn--85x722f', $domain->getPublicSuffix());
    }

    public function testRegistrableDomainIsNullWithNoMatchedDomain()
    {
        $domain = new UnmatchedDomain('xn--85x722f', 'xn--85x722f');
        $this->assertFalse($domain->isValid());
        $this->assertNull($domain->getRegistrableDomain());
    }

    /**
     * @dataProvider parseDataProvider
     */
    public function testGetRegistrableDomain($publicSuffix, $registrableDomain, $domain, $expectedDomain)
    {
        $this->assertSame($registrableDomain, $this->list->query($domain)->getRegistrableDomain());
    }

    /**
     * @dataProvider parseDataProvider
     */
    public function testGetPublicSuffix($publicSuffix, $registrableDomain, $domain, $expectedDomain)
    {
        $this->assertSame($publicSuffix, $this->list->query($domain)->getPublicSuffix());
    }

    /**
     * @dataProvider parseDataProvider
     */
    public function testGetDomain($publicSuffix, $registrableDomain, $domain, $expectedDomain)
    {
        $this->assertSame($expectedDomain, $this->list->query($domain)->getDomain());
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
            'biz tld (2)' => ['webhop.biz', 'broken.webhop.biz', 'www.broken.webhop.biz', 'www.broken.webhop.biz'],
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

        $this->assertSame($publicSuffix, $this->list->query($domain)->getPublicSuffix());
    }

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
        $this->checkPublicSuffix('uk.com', null);
        $this->checkPublicSuffix('example.uk.com', 'example.uk.com');
        $this->checkPublicSuffix('b.example.uk.com', 'example.uk.com');
        $this->checkPublicSuffix('a.b.example.uk.com', 'example.uk.com');
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

    /**
     * Checks PublicSuffixList can return proper registrable domain.
     *
     * "You will need to define a checkPublicSuffix() function which takes as a
     * parameter a domain name and the public suffix, runs your implementation
     * on the domain name and checks the result is the public suffix expected."
     *
     * @see http://publicsuffix.org/list/
     *
     * @param string $domain
     * @param string $expected
     */
    private function checkPublicSuffix($domain, $expected)
    {
        $this->assertSame(
            $expected,
            $this->list->query($domain)->getRegistrableDomain()
        );
    }
}
