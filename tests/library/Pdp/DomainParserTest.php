<?php

namespace Pdp;

class DomainParserTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;

    protected function setUp()
    {
        parent::setUp();
        $publicSuffixListArray = include PDP_TEST_ROOT . '/_files/' . PublicSuffixListManager::PDP_PSL_PHP_FILE;
        $this->parser = new DomainParser(
            new PublicSuffixList($publicSuffixListArray)
        );
    }

    protected function tearDown()
    {
        $this->parser = null;
        parent::tearDown();
    }

    /**
     * @covers \Pdp\DomainParser::parse()
     * @covers \Pdp\DomainParser::getPublicSuffix()
     * @covers \Pdp\DomainParser::getRegisterableDomain()
     * @covers \Pdp\DomainParser::breakdown()
     * @dataProvider parseDataProvider
     */
    public function testParse($url, $scheme, $publicSuffix, $registerableDomain, $subdomain, $path)
    {
        $domain = $this->parser->parse($url);

        $this->assertInstanceOf('\Pdp\Domain', $domain);
        $this->assertEquals($url, $domain->getUrl());
        $this->assertEquals($scheme, $domain->getScheme());
        $this->assertEquals($publicSuffix, $domain->getPublicSuffix());
        $this->assertEquals($registerableDomain, $domain->getRegisterableDomain());
        $this->assertEquals($subdomain, $domain->getSubdomain());
        // This assertion ensures null subdomains actually are null 
        $this->assertTrue($subdomain === $domain->getSubdomain());
        $this->assertEquals($path, $domain->getPath());
    }

    /**
     * @covers \Pdp\DomainParser::getPublicSuffix()
     * @dataProvider parseDataProvider
     */
    public function testGetPublicSuffix($url, $scheme, $publicSuffix, $registerableDomain, $subdomain, $path)
    {
        $this->assertEquals($publicSuffix, $this->parser->getPublicSuffix($registerableDomain));
    }

    public function parseDataProvider()
    {
        return array(
            array('http://www.waxaudio.com.au/audio/albums/the_mashening', 'http', 'com.au', 'waxaudio.com.au', 'www', '/audio/albums/the_mashening'),
            array('example.com', null, 'com', 'example.com', null, null),
            array('cea-law.co.il', null, 'co.il', 'cea-law.co.il', null, null),
            array('http://edition.cnn.com/WORLD/', 'http', 'com', 'cnn.com', 'edition', '/WORLD/'),
            array('http://en.wikipedia.org/', 'http', 'org', 'wikipedia.org', 'en', null),
            array('a.b.c.cy', null, 'c.cy', 'b.c.cy', 'a', null),
            array('https://test.k12.ak.us', 'https', 'k12.ak.us', 'test.k12.ak.us', null, null),
            array('www.scottwills.co.uk', null, 'co.uk', 'scottwills.co.uk', 'www', null),
            array('b.ide.kyoto.jp', null, 'ide.kyoto.jp', 'b.ide.kyoto.jp', null, null),
            array('a.b.example.uk.com', null, 'uk.com', 'example.uk.com', 'a.b', null),
            array('test.nic.ar', null, 'nic.ar', 'test.nic.ar', null, null),
            array('a.b.test.om', null, 'test.om', 'b.test.om', 'a', null),
            array('baez.songfest.om', null, 'songfest.om', 'baez.songfest.om', null, null),
            array('politics.news.omanpost.om', null, 'omanpost.om', 'news.omanpost.om', 'politics', null),
        );
    }
	
    /**
     * @todo Add test cases to data provider
     *
     * @covers \Pdp\DomainParser::getPublicSuffix()
     * @dataProvider domainSuffixDataProvider
     */
    public function testGetPublicSuffixSpecialCases($input, $expected)
    {
        $this->assertEquals($expected, $this->parser->getPublicSuffix($input));
    }

    public function domainSuffixDataProvider() 
    {
        return array(
            array('com', null),
        );
    }
}
