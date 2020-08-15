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

use Pdp\Storage\Http\CurlClient;
use Pdp\Storage\Http\CurlClientException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Pdp\Storage\Http\CurlClient
 */
final class CurlHttpClientTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::getContent
     */
    public function testGetContent(): void
    {
        $content = (new \Pdp\Storage\Http\CurlClient())->getContent('https://www.google.com');

        self::assertStringContainsString('google', $content);
    }

    /**
     * @covers ::__construct
     * @covers ::getContent
     */
    public function testThrowsException(): void
    {
        self::expectException(CurlClientException::class);
        (new CurlClient())->getContent('https://qsfsdfqdf.dfsf');
    }

    /**
     * @covers ::__construct
     */
    public function testConstructorThrowsException(): void
    {
        self::expectException(CurlClientException::class);
        new CurlClient(['foo' => 'bar']);
    }
}
