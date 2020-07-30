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

namespace Pdp\Tests\Storage;

use Pdp\Storage\CurlHttpClient;
use Pdp\Storage\CurlHttpHttpClientException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Pdp\Storage\CurlHttpClient
 */
final class CurlHttpClientTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::getContent
     */
    public function testGetContent(): void
    {
        $content = (new CurlHttpClient())->getContent('https://www.google.com');

        self::assertStringContainsString('google', $content);
    }

    /**
     * @covers ::__construct
     * @covers ::getContent
     */
    public function testThrowsException(): void
    {
        self::expectException(CurlHttpHttpClientException::class);
        (new CurlHttpClient())->getContent('https://qsfsdfqdf.dfsf');
    }

    /**
     * @covers ::__construct
     */
    public function testConstructorThrowsException(): void
    {
        self::expectException(CurlHttpHttpClientException::class);
        new CurlHttpClient(['foo' => 'bar']);
    }
}
