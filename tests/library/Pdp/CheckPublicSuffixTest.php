<?php

namespace Pdp;

/**
 * This test case is based on the test data linked at 
 * http://publicsuffix.org/list/ and provided by Rob Strading of Comodo.
 * @link 
 * http://mxr.mozilla.org/mozilla-central/source/netwerk/test/unit/data/test_psl.txt?raw=1
 */
class CheckPublicSuffixTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;

    protected function setUp()
    {
        $file = realpath(dirname(__DIR__) . '/../../data/public-suffix-list.php');
        $this->parser = new Parser(new PublicSuffixList($file));
    }

    protected function tearDown()
    {
        $this->parser = null;
    }

    public function testPublicSuffixSpec()
    {
        // Test data from Rob Stradling at Comodo
        // http://mxr.mozilla.org/mozilla-central/source/netwerk/test/unit/data/test_psl.txt?raw=1

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
        $this->checkPublicSuffix('cy', null);
        $this->checkPublicSuffix('c.cy', null);
        $this->checkPublicSuffix('b.c.cy', 'b.c.cy');
        $this->checkPublicSuffix('a.b.c.cy', 'b.c.cy');
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
        $this->checkPublicSuffix('om', null);
        $this->checkPublicSuffix('test.om', null);
        $this->checkPublicSuffix('b.test.om', 'b.test.om');
        $this->checkPublicSuffix('a.b.test.om', 'b.test.om');
        $this->checkPublicSuffix('songfest.om', 'songfest.om');
        $this->checkPublicSuffix('www.songfest.om', 'songfest.om');
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
    }

    /**
     * This is my version of the checkPublicSuffix function referred to in the 
     * test instructions at the Public Suffix List project.
     *
     * "You will need to define a checkPublicSuffix() function which takes as a 
     * parameter a domain name and the public suffix, runs your implementation 
     * on the domain name and checks the result is the public suffix expected."
     *
     * @link http://publicsuffix.org/list/
     *
     * @param string $input Domain and public suffix
     * @param string $expected Expected result
     */
    public function checkPublicSuffix($input, $expected)
    {
        $this->assertSame($expected, $this->parser->getRegisterableDomain($input));
    }
}
