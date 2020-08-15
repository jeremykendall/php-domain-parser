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

use Pdp\PublicSuffixList;
use Pdp\PublicSuffixListConverter;
use Pdp\Rules;
use function json_encode;

final class PublicSuffixListCurlClient implements PublicSuffixListClient
{
    private Client $client;

    private PublicSuffixListConverter $converter;

    public function __construct(Client $client, PublicSuffixListConverter $converter)
    {
        $this->client = $client;
        $this->converter = $converter;
    }

    public function getByUri(string $uri = self::PSL_URL): PublicSuffixList
    {
        $rawBody = $this->client->getContent($uri);
        $rawPsl = $this->converter->convert($rawBody);

        /** @var string $jsonEncodedPsl */
        $jsonEncodedPsl = json_encode($rawPsl);

        return Rules::fromJsonString($jsonEncodedPsl);
    }
}
