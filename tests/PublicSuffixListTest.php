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
namespace Pdp\Tests;

use OutOfRangeException;
use Pdp\Cache\FileCacheAdapter;
use Pdp\Http\CurlHttpAdapter;
use Pdp\MatchedDomain;
use Pdp\NullDomain;
use Pdp\PublicSuffixList;
use Pdp\PublicSuffixListManager;
use Pdp\UnmatchedDomain;
use PHPUnit\Framework\TestCase;

class PublicSuffixListTest extends TestCase
{
    /**
     * @var PublicSuffixList
     */
    private $list;

    private $manager;

    public function setUp()
    {
        $this->manager = new PublicSuffixListManager(new FileCacheAdapter(), new CurlHttpAdapter());
        $this->list = $this->manager->getList();
    }

    public function testConstructorThrowsExceptionOnUnsupportedType()
    {
        $this->expectException(OutOfRangeException::class);
        new PublicSuffixList('foo', []);
    }

    public function testFullDomainList()
    {
        $this->assertTrue($this->list->isAll());
        $this->assertFalse($this->list->isICANN());
        $this->assertFalse($this->list->isPrivate());
    }

    public function testICANNDomainList()
    {
        $list = $this->manager->getList(PublicSuffixList::ICANN_DOMAINS);
        $this->assertFalse($list->isAll());
        $this->assertTrue($list->isICANN());
        $this->assertFalse($list->isPrivate());
    }

    public function testPrivateDomainList()
    {
        $list = $this->manager->getList(PublicSuffixList::PRIVATE_DOMAINS);
        $this->assertFalse($list->isAll());
        $this->assertFalse($list->isICANN());
        $this->assertTrue($list->isPrivate());
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
}
