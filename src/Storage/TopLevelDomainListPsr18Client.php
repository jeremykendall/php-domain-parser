<?php

declare(strict_types=1);

namespace Pdp\Storage;

use Pdp\TopLevelDomainList;
use Pdp\TopLevelDomains;
use Pdp\UnableToLoadResource;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

final class TopLevelDomainListPsr18Client implements TopLevelDomainListClient
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory
    ) {
    }

    public function get(string $uri): TopLevelDomainList
    {
        $request = $this->requestFactory->createRequest('GET', $uri);
        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw UnableToLoadResource::dueToUnavailableService($uri, $exception);
        }

        if (400 <= $response->getStatusCode()) {
            throw UnableToLoadResource::dueToUnexpectedStatusCode($uri, $response->getStatusCode());
        }

        return TopLevelDomains::fromString($response->getBody());
    }
}
