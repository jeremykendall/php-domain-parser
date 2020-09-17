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

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Pdp\Storage\RootZoneDatabaseRemoteStorage;
use Pdp\TopLevelDomains;
use Pdp\UnableToLoadRootZoneDatabase;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use function dirname;
use function file_get_contents;

/**
 * @coversDefaultClass \Pdp\Storage\RootZoneDatabaseRemoteStorage
 */
final class RootZoneDatabaseRemoteStorageTest extends TestCase
{
    public function testIsCanReturnARootZoneDatabaseInstance(): void
    {
        $client = new class() implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                /** @var string $body */
                $body = file_get_contents(dirname(__DIR__, 2).'/test_data/tlds-alpha-by-domain.txt');

                return new Response(200, [], $body);
            }
        };

        $requestFactory = new class() implements RequestFactoryInterface {
            public function createRequest(string $method, $uri): RequestInterface
            {
                return new Request($method, $uri);
            }
        };

        $storage = new RootZoneDatabaseRemoteStorage($client, $requestFactory);
        $rzd = $storage->getByUri('http://www.example.com');

        self::assertInstanceOf(TopLevelDomains::class, $rzd);
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

        self::expectException(UnableToLoadRootZoneDatabase::class);
        self::expectExceptionMessage('Could not access the Root Zone Database URI: `http://www.example.com`.');

        $storage = new RootZoneDatabaseRemoteStorage($client, $requestFactory);
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

        self::expectException(UnableToLoadRootZoneDatabase::class);
        self::expectExceptionMessage('Invalid response from Root Zone Database URI: `http://www.example.com`.');
        self::expectExceptionCode(404);

        $storage = new RootZoneDatabaseRemoteStorage($client, $requestFactory);
        $storage->getByUri('http://www.example.com');
    }
}
