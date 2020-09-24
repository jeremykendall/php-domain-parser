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

namespace Pdp\Storage;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Pdp\Rules;
use Pdp\UnableToLoadPublicSuffixList;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use function dirname;
use function file_get_contents;

/**
 * @coversDefaultClass \Pdp\Storage\RulesPsr18Client
 */
final class RulesPsr18ClientTest extends TestCase
{
    public function testIsCanReturnAPublicSuffixListInstance(): void
    {
        $client = new class() implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                /** @var string $body */
                $body = file_get_contents(dirname(__DIR__, 2).'/test_data/public_suffix_list.dat');
                return new Response(200, [], $body);
            }
        };

        $requestFactory = new class() implements RequestFactoryInterface {
            public function createRequest(string $method, $uri): RequestInterface
            {
                return new Request($method, $uri);
            }
        };

        $storage = new RulesPsr18Client($client, $requestFactory);
        $psl = $storage->getByUri('http://www.example.com');

        self::assertInstanceOf(Rules::class, $psl);
    }

    public function testItWillThrowIfTheClientCanNotConnectToTheRemoteURI(): void
    {
        $client = new class() implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new ConnectException('foobar', $request, null);
            }
        };

        $requestFactory = new class() implements RequestFactoryInterface {
            public function createRequest(string $method, $uri): RequestInterface
            {
                return new Request($method, $uri);
            }
        };

        self::expectException(UnableToLoadPublicSuffixList::class);
        self::expectExceptionMessage('Could not access the Public Suffix List URI: `http://www.example.com`.');

        $storage = new RulesPsr18Client($client, $requestFactory);
        $storage->getByUri('http://www.example.com');
    }

    public function testItWillThrowIfTheReturnedStatusCodeIsNotOK(): void
    {
        $client = new class() implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(404);
            }
        };

        $requestFactory = new class() implements RequestFactoryInterface {
            public function createRequest(string $method, $uri): RequestInterface
            {
                return new Request($method, $uri);
            }
        };

        self::expectException(UnableToLoadPublicSuffixList::class);
        self::expectExceptionMessage('Invalid response from Public Suffix List URI: `http://www.example.com`.');
        self::expectExceptionCode(404);

        $storage = new RulesPsr18Client($client, $requestFactory);
        $storage->getByUri('http://www.example.com');
    }
}
