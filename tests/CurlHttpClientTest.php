<?php

/**
 * PHP Domain Parser: Public Suffix List based URL parsing.
 *
 * @see http://github.com/jeremykendall/php-domain-parser for the canonical source repository
 *
 * @copyright Copyright (c) 2017 Jeremy Kendall (http://jeremykendall.net)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        self::assertNotNull($content);
        self::assertContains('google', $content);
    }

    /**
     * @covers ::__construct
     * @covers ::getContent
     */
    public function testThrowsException()
    {
        self::expectException(HttpClientException::class);
        (new CurlHttpClient())->getContent('https://qsfsdfqdf.dfsf');
    }

    /**
     * @covers ::__construct
     */
    public function testConstructorThrowsException()
    {
        self::expectException(Exception::class);
        new CurlHttpClient(['foo' => 'bar']);
    }
}
