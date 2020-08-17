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

use Pdp\PublicSuffixList;
use Pdp\PublicSuffixListConverter;
use Pdp\Rules;
use Pdp\UnableToLoadPublicSuffixList;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use function json_encode;

final class PublicSuffixListClientPsr18Adapter implements PublicSuffixListClient
{
    private ClientInterface $client;

    private RequestFactoryInterface $requestFactory;

    private PublicSuffixListConverter $converter;

    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        PublicSuffixListConverter $converter
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->converter = $converter;
    }

    public function getByUri(string $uri): PublicSuffixList
    {
        $request = $this->requestFactory->createRequest('GET', $uri);
        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw UnableToLoadPublicSuffixList::dueToUnavailableService($uri, $exception);
        }

        if (400 <= $response->getStatusCode()) {
            throw UnableToLoadPublicSuffixList::dueToUnexpectedContent($uri, $response->getStatusCode());
        }

        $rawPsl = $this->converter->convert($response->getBody());

        /** @var string $jsonEncodedPsl */
        $jsonEncodedPsl = json_encode($rawPsl);

        return Rules::fromJsonString($jsonEncodedPsl);
    }
}
