<?php

namespace Pdp;

class DomainParserTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;

    protected function setUp()
    {
        parent::setUp();
        $publicSuffixListArray = include PDP_TEST_ROOT . '/_files/public_suffix_list.php';
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
     */
    public function testParse()
    {
        $url = 'http://www.waxaudio.com.au/audio/albums/the_mashening';
        $domain = $this->parser->parse($url);

        $this->assertInstanceOf('\Pdp\Domain', $domain);
        $this->assertEquals($url, $domain->getUrl());
        $this->assertEquals('http', $domain->getScheme());
        $this->assertEquals('com.au', $domain->getPublicSuffix());
        $this->assertEquals('waxaudio.com.au', $domain->getRegisterableDomain());
        $this->assertEquals('www', $domain->getSubdomain());
        $this->assertEquals('/audio/albums/the_mashening', $domain->getPath());
    }
	
    /**
     * @covers \Pdp\DomainParser::getPublicSuffix()
     * @dataProvider domainSuffixDataProvider
     */
    public function testGetDomainSuffix($domain, $suffix)
    {
        $this->assertEquals($suffix, $this->parser->getPublicSuffix($domain));
    }

    public function domainSuffixDataProvider() 
    {
        return array(
            array('example.com', 'com'),
            array('scottwills.co.uk', 'co.uk'),
            array('waxaudio.com.au', 'com.au'),
            array('cea-law.co.il', 'co.il'),
            array('http://en.wikipedia.org', 'org'),
            array('http://new.monday.cnn.com', 'com'),
            array('b.ide.kyoto.jp', 'ide.kyoto.jp'),
            array('a.b.c.cy', 'c.cy'),
            array('a.b.example.uk.com', 'uk.com'),
            array('test.k12.ak.us', 'k12.ak.us'),
            array('test.nic.ar', 'nic.ar'),
            array('a.b.test.om', 'test.om'),
            array('baez.songfest.om', 'songfest.om'),
            array('politics.news.omanpost.om', 'omanpost.om'),
            array('com', null),
        );
    }
}
