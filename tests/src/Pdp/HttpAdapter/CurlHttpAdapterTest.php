<?php

namespace Pdp\HttpAdapter;

use PHPUnit\Framework\TestCase;

/**
 * @group internet
 */
class CurlHttpAdapterTest extends TestCase
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
        $content = $this->adapter->getContent('https://www.google.com');
        $this->assertNotNull($content);
        $this->assertContains('google', $content);
    }
}
