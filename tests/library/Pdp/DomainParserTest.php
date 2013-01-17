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
     * @covers \Pdp\DomainParser::getRegisterableDomain()
     * @covers \Pdp\DomainParser::breakdown()
     * @dataProvider parseDataProvider
     */
    public function testParse($url, $publicSuffix, $registerableDomain, $subdomain)
    {
        $domain = $this->parser->parse($url);

        $this->assertInstanceOf('\Pdp\Domain', $domain);
        
        // This assertion ensures null subdomains actually are null 
        $this->assertTrue($subdomain === $domain->getSubdomain());
        
        $this->assertEquals($publicSuffix, $domain->getPublicSuffix());
        $this->assertEquals($registerableDomain, $domain->getRegisterableDomain());
    }

    public function parseDataProvider()
    {
        return array(
            array('http://www.waxaudio.com.au/audio/albums/the_mashening', 'com.au', 'waxaudio.com.au', 'www'),
            array('example.com', 'com', 'example.com', null),
            array('cea-law.co.il', 'co.il', 'cea-law.co.il', null),
            array('http://edition.cnn.com/WORLD/', 'com', 'cnn.com', 'edition'),
            array('http://en.wikipedia.org/', 'org', 'wikipedia.org', 'en'),
            array('a.b.c.cy', 'c.cy', 'b.c.cy', 'a'),
            array('https://test.k12.ak.us', 'k12.ak.us', 'test.k12.ak.us', null),
            array('www.scottwills.co.uk', 'co.uk', 'scottwills.co.uk', 'www'),
            array('b.ide.kyoto.jp', 'ide.kyoto.jp', 'b.ide.kyoto.jp', null),
            array('a.b.example.uk.com', 'uk.com', 'example.uk.com', 'a.b'),
            array('test.nic.ar', 'ar', 'nic.ar', 'test'),
            array('a.b.test.om', 'test.om', 'b.test.om', 'a', null),
            array('baez.songfest.om', 'om', 'songfest.om', 'baez'),
            array('politics.news.omanpost.om', 'om', 'omanpost.om', 'politics.news'),
        );
    }
	
}
