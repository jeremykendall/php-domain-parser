<?php

namespace Pdp;

class DomainParserTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;

    protected $publicSuffixFile;

    protected function setUp()
    {
        $this->parser = new DomainParser();
        $this->publicSuffixFile = PDP_TEST_ROOT . '/_files/public_suffix_list.txt';
        $this->assertFileExists($this->publicSuffixFile);
    }

    protected function tearDown()
    {
        $this->parser = null;
    }
    
    /**
     * @group parse
     */
    public function testParsePublicSuffixList()
    {
        $publicSuffixList = $this->parser->parsePublicSuffixList($this->publicSuffixFile);
        $this->assertInternalType('array', $publicSuffixList);
        $this->assertGreaterThanOrEqual(6000, count($publicSuffixList));
        $this->assertTrue(array_search('stuff-4-sale.org', $publicSuffixList) !== false);
        $this->assertTrue(array_search('net.ac', $publicSuffixList) !== false);
    }

    /**
     * @group wtf
     * @dataProvider domainSuffixDataProvider
     */
    public function testGetDomainSuffix($domain, $suffix)
    {
        $this->assertEquals($suffix, $this->parser->getDomainSuffix($domain));
    }

    /**
     * @group faster
     * @dataProvider domainSuffixDataProvider
     */
    public function testGetDomainSuffixFromArray($domain, $suffix)
    {
        $list = include PDP_TEST_ROOT . '/_files/publicSuffixList.php';
        $components = explode('.', $domain);
        
        $this->assertEquals($suffix, $this->parser->getDomainSuffixFromArray($components, $list));
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
