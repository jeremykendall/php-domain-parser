<?php

declare(strict_types=1);

namespace Pdp\Tests;

use Pdp\CurlHttpClient;
use Pdp\Exception;
use Pdp\HttpClientException;
use PHPUnit\Framework\TestCase;

class CurlHttpClientTest extends TestCase
{
    public function testGetContent()
    {
        $content = (new CurlHttpClient())->getContent('https://www.google.com');
        $this->assertNotNull($content);
        $this->assertContains('google', $content);
    }

    public function testThrowsException()
    {
        $this->expectException(HttpClientException::class);
        (new CurlHttpClient())->getContent('https://qsfsdfqdf.dfsf');
    }

    public function testConstructorThrowsException()
    {
        $this->expectException(Exception::class);
        new CurlHttpClient(['foo' => 'bar']);
    }
}
