<?php

declare(strict_types=1);

namespace Pdp\Tests;

use Pdp\CurlHttpClient;
use Pdp\Exception;
use Pdp\HttpClientException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Pdp\CurlHttpClient
 */
class CurlHttpClientTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::getContent
     */
    public function testGetContent()
    {
        $content = (new CurlHttpClient())->getContent('https://www.google.com');
        $this->assertNotNull($content);
        $this->assertContains('google', $content);
    }

    /**
     * @covers ::__construct
     * @covers ::getContent
     */
    public function testThrowsException()
    {
        $this->expectException(HttpClientException::class);
        (new CurlHttpClient())->getContent('https://qsfsdfqdf.dfsf');
    }

    /**
     * @covers ::__construct
     */
    public function testConstructorThrowsException()
    {
        $this->expectException(Exception::class);
        new CurlHttpClient(['foo' => 'bar']);
    }
}
