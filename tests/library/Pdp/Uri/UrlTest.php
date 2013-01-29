<?php

namespace Pdp\Uri;

use Pdp\PublicSuffixList;
use Pdp\Uri\Url\Host;
use Pdp\PublicSuffixListManager;
use Pdp\Parser;

class UrlTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Url
     */
    protected $url;
    
    /**
     * @var string Url spec
     */
    protected $spec = 'http://anonymous:guest@example.com:8080/path/to/index.php/foo/bar.xml?baz=dib#anchor';

    /**
     * @var PublicSuffixList Public Suffix List
     */
    protected $psl;

    protected function setUp()
    {
        parent::setUp();
        $file = realpath(dirname(__DIR__) . '/../../../data/' . PublicSuffixListManager::PDP_PSL_PHP_FILE); 
        $psl = new PublicSuffixList($file);
        $parser = new Parser($psl);
        $this->url = $parser->parseUrl($this->spec);
    }

    protected function tearDown()
    {
        parent::tearDown();
    }

    public function test__construct()
    {
        $url = new Url(
            'http',
            'anonymous',
            'guest',
            new Host(
                null, 
                'example.com', 
                'com'
            ),
            null,
            '/path/to/index.php/foo/bar.xml',
            'baz=dib',
            'anchor'
        );
        
        $this->assertInstanceOf('Pdp\Uri\Url', $url);
    }

    public function test__toString()
    {
        $this->assertEquals($this->spec, $this->url->__toString());
    }

    public function test__getProperties()
    {
        $expected = array(
            'scheme' => 'http',
            'user' => 'anonymous',
            'pass' => 'guest',
            'host' => 'example.com',
            'port' => 8080,
            'path' => '/path/to/index.php/foo/bar.xml',
            'query' => 'baz=dib',
            'fragment' => 'anchor'
        );

        $this->assertEquals($expected['scheme'], $this->url->scheme);
        $this->assertEquals($expected['user'], $this->url->user);
        $this->assertEquals($expected['pass'], $this->url->pass);
        $this->assertEquals($expected['host'], $this->url->host->__toString());
        $this->assertEquals($expected['port'], $this->url->port);
        $this->assertEquals($expected['path'], $this->url->path);
        $this->assertEquals($expected['query'], $this->url->query);
        $this->assertEquals($expected['fragment'], $this->url->fragment);
    }
    
}
