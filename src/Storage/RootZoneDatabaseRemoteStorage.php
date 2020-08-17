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

use Pdp\RootZoneDatabase;
use Pdp\RootZoneDatabaseConverter;
use Pdp\TopLevelDomains;
use Pdp\UnableToLoadRootZoneDatabase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use function json_encode;

final class RootZoneDatabaseRemoteStorage implements RootZoneDatabaseStorage
{
    private ClientInterface $client;

    private RequestFactoryInterface $requestFactory;

    private RootZoneDatabaseConverter $converter;

    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        RootZoneDatabaseConverter $converter
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->converter = $converter;
    }

    public function getByUri(string $uri): RootZoneDatabase
    {
        $request = $this->requestFactory->createRequest('GET', $uri);
        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw UnableToLoadRootZoneDatabase::dueToUnavailableService($uri, $exception);
        }

        if (400 <= $response->getStatusCode()) {
            throw UnableToLoadRootZoneDatabase::dueToUnexpectedContent($uri, $response->getStatusCode());
        }

        $rawRzd = $this->converter->convert($response->getBody());

        /** @var string $jsonEncodedRzd */
        $jsonEncodedRzd = json_encode($rawRzd);

        return TopLevelDomains::fromJsonString($jsonEncodedRzd);
    }
}
