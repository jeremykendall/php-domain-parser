<?php

namespace Pdp;

class DomainParserTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;

    protected function setUp()
    {
		$publicSuffixListManager = new PublicSuffixListManager(PDP_TEST_ROOT . '/_files');
		$publicSuffixList = new PublicSuffixList($publicSuffixListManager->getList());
        $this->parser = new DomainParser($publicSuffixList);
    }

    protected function tearDown()
    {
        $this->parser = null;
    }
	
    /**
     * @group wtf
     * @dataProvider domainSuffixDataProvider
     */
    public function testGetDomainSuffix($domain, $suffix)
    {
        $this->assertEquals($suffix, $this->parser->getDomainSuffix($domain));
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
        );
    }
}
