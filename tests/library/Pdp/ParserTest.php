<?php

namespace Pdp;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;

    protected function setUp()
    {
        parent::setUp();
        $publicSuffixListArray = include PDP_TEST_ROOT . '/_files/' . PublicSuffixListManager::PDP_PSL_PHP_FILE;
        $this->parser = new Parser(
            new PublicSuffixList($publicSuffixListArray)
        );
    }

    protected function tearDown()
    {
        $this->parser = null;
        parent::tearDown();
    }

    /**
     * @covers Pdp\Parser::parse()
     * @dataProvider parseDataProvider
     */
    public function testParse($url, $publicSuffix, $registerableDomain, $subdomain, $hostPart)
    {
        $host = $this->parser->parse($url);

        $this->assertInstanceOf('\Pdp\Host', $host);
        
        $this->assertSame($subdomain, $host->getSubdomain());
        $this->assertEquals($publicSuffix, $host->getPublicSuffix());
        $this->assertEquals($registerableDomain, $host->getRegisterableDomain());
    }

    /**
     * @covers Pdp\Parser::getPublicSuffix()
     * @dataProvider parseDataProvider
     */
    public function testGetPublicSuffix($url, $publicSuffix, $registerableDomain, $subdomain, $hostPart)
    {
        $this->assertSame($publicSuffix, $this->parser->getPublicSuffix($hostPart));
    }

    /**
     * @covers Pdp\Parser::getRegisterableDomain()
     * @dataProvider parseDataProvider
     */
    public function testGetRegisterableDomain($url, $publicSuffix, $registerableDomain, $subdomain, $hostPart)
    {
        $this->assertSame($registerableDomain, $this->parser->getRegisterableDomain($hostPart));
    }

    /**
     * @covers Pdp\Parser::getSubdomain()
     * @dataProvider parseDataProvider
     */
    public function testGetSubdomain($url, $publicSuffix, $registerableDomain, $subdomain, $hostPart)
    {
        $this->assertSame($subdomain, $this->parser->getSubdomain($hostPart));
    }
    
	/**
     * @dataProvider parseDataProvider
	 */
	public function testPHPparse_urlCanReturnCorrectHost($url, $publicSuffix, $registerableDomain, $subdomain, $hostPart)
	{
		$this->assertEquals($hostPart, parse_url('http://' . $hostPart, PHP_URL_HOST));
	}

    public function parseDataProvider()
    {
        return array(
            array('http://www.waxaudio.com.au/audio/albums/the_mashening', 'com.au', 'waxaudio.com.au', 'www', 'www.waxaudio.com.au'),
            array('example.com', 'com', 'example.com', null, 'example.com'),
            array('giant.yyyy', 'yyyy', 'giant.yyyy', null, 'giant.yyyy'),
            array('cea-law.co.il', 'co.il', 'cea-law.co.il', null, 'cea-law.co.il'),
            array('http://edition.cnn.com/WORLD/', 'com', 'cnn.com', 'edition', 'edition.cnn.com'),
            array('http://en.wikipedia.org/', 'org', 'wikipedia.org', 'en', 'en.wikipedia.org'),
            array('a.b.c.cy', 'c.cy', 'b.c.cy', 'a', 'a.b.c.cy'),
            array('https://test.k12.ak.us', 'k12.ak.us', 'test.k12.ak.us', null, 'test.k12.ak.us'),
            array('www.scottwills.co.uk', 'co.uk', 'scottwills.co.uk', 'www', 'www.scottwills.co.uk'),
            array('b.ide.kyoto.jp', 'ide.kyoto.jp', 'b.ide.kyoto.jp', null, 'b.ide.kyoto.jp'),
            array('a.b.example.uk.com', 'uk.com', 'example.uk.com', 'a.b', 'a.b.example.uk.com'),
            array('test.nic.ar', 'ar', 'nic.ar', 'test', 'test.nic.ar'),
            array('a.b.test.om', 'test.om', 'b.test.om', 'a', 'a.b.test.om'),
            array('baez.songfest.om', 'om', 'songfest.om', 'baez', 'baez.songfest.om'),
            array('politics.news.omanpost.om', 'om', 'omanpost.om', 'politics.news', 'politics.news.omanpost.om'),
        );
    }
	
}
