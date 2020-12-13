<?php

declare(strict_types=1);

namespace Pdp\Storage;

use Pdp\PublicSuffixList;
use Pdp\Rules;
use Pdp\UnableToLoadResource;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

final class PublicSuffixListPsr18Client implements PublicSuffixListClient
{
    private ClientInterface $client;

    private RequestFactoryInterface $requestFactory;

    public function __construct(ClientInterface $client, RequestFactoryInterface $requestFactory)
    {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
    }

    public function get(string $uri): PublicSuffixList
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

        return Rules::fromString($response->getBody());
    }
}
