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

namespace Pdp\Storage\Http;

use Pdp\RootZoneDatabase;
use Pdp\RootZoneDatabaseConverter;
use Pdp\TopLevelDomains;
use function json_encode;

final class RootZoneDatabaseCurlClient implements RootZoneDatabaseClient
{
    private Client $client;

    private RootZoneDatabaseConverter $converter;

    public function __construct(Client $client, RootZoneDatabaseConverter $converter)
    {
        $this->client = $client;
        $this->converter = $converter;
    }

    public function getByUri(string $uri = self::RZD_URL): RootZoneDatabase
    {
        $rawBody = $this->client->getContent($uri);
        $rawRzd = $this->converter->convert($rawBody);

        /** @var string $jsonEncodedRzd */
        $jsonEncodedRzd = json_encode($rawRzd);

        return TopLevelDomains::fromJsonString($jsonEncodedRzd);
    }
}
