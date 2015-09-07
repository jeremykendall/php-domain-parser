<?php

namespace Pdp\HttpAdapter;


use Pdp\HttpAdapter\Exception;

/**
 * @group internet
 */
class CurlHttpAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var HttpAdapterInterface
     */
    protected $adapter;

    protected function setUp()
    {
        if (!function_exists('curl_init')) {
            $this->markTestSkipped('cURL has to be enabled.');
        }

        $this->adapter = new CurlHttpAdapter();
    }

    protected function tearDown()
    {
        $this->adapter = null;
    }

    public function testGetContent()
    {
        $content = $this->adapter->getContent('http://www.google.com');
        $this->assertNotNull($content);
        $this->assertContains('google', $content);
    }

    public function testGetContentIgnoringSsl()
    {
        $content = $this->adapter->getContent('https://www.google.com', true);
        $this->assertNotNull($content);
        $this->assertContains('google', $content);
    }

    public function testExceptionBadUrl()
    {
        $this->setExpectedException('Pdp\HttpAdapter\Exception\HttpAdapterException', '', CURLE_COULDNT_RESOLVE_HOST);
        $content = $this->adapter->getContent('https://aaaa.aaaa');
    }

    public function testExceptionBadHttpsCertificate()
    {
        $this->setExpectedException('Pdp\HttpAdapter\Exception\HttpAdapterException', '', CURLE_SSL_PEER_CERTIFICATE);
        $content = $this->adapter->getContent('https://tv.eurosport.com/');
    }
}
