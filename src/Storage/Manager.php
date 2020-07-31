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

use Pdp\PublicSuffixListInterface;
use Pdp\RootZoneDatabaseInterface;
use Pdp\Rules;
use Pdp\TopLevelDomains;
use Pdp\UnableToLoadPublicSuffixList;
use Pdp\UnableToLoadRootZoneDatabase;

final class Manager
{
    private const PSL_URL = 'https://publicsuffix.org/list/public_suffix_list.dat';
    private const RZD_URL = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';

    private HttpClient $http;

    private PublicSuffixListCache $publicSuffixListCache;

    private RootZoneDatabaseCache $rootZoneDatabaseCache;

    public function __construct(HttpClient $http, PublicSuffixListCache $rulesCache, RootZoneDatabaseCache $topLevelDomainsCache)
    {
        $this->http = $http;
        $this->publicSuffixListCache = $rulesCache;
        $this->rootZoneDatabaseCache = $topLevelDomainsCache;
    }

    /**
     * Gets the Public Suffix List from the Local Storage or the Remote Storage.
     *
     * @throws UnableToLoadPublicSuffixList
     */
    public function getPublicSuffixListLocalCopy(string $uri = self::PSL_URL): PublicSuffixListInterface
    {
        return $this->publicSuffixListCache->fetchByUri($uri) ?? $this->getPublicSuffixListRemoteCopy($uri);
    }

    /**
     * Gets the Public Suffix List from an the Remote Storage.
     *
     * @throws UnableToLoadPublicSuffixList
     */
    public function getPublicSuffixListRemoteCopy(string $uri = self::PSL_URL): PublicSuffixListInterface
    {
        $rules = Rules::fromString($this->http->getContent($uri));

        $this->publicSuffixListCache->storeByUri($uri, $rules);

        return $rules;
    }

    /**
     * Gets the Top Level Domains from the Local Storage or the Remote Storage.
     *
     * @throws UnableToLoadRootZoneDatabase
     */
    public function getRootZoneDatabaseLocalCopy(string $uri = self::RZD_URL): RootZoneDatabaseInterface
    {
        return $this->rootZoneDatabaseCache->fetchByUri($uri) ?? $this->getRootZoneDatabaseRemoteCopy($uri);
    }

    /**
     * Gets the Top Level Domains from the Remote Storage.
     *
     * @throws UnableToLoadRootZoneDatabase
     */
    public function getRootZoneDatabaseRemoteCopy(string $uri = self::RZD_URL): RootZoneDatabaseInterface
    {
        $rootZoneDatabase = TopLevelDomains::fromString($this->http->getContent($uri));

        $this->rootZoneDatabaseCache->storeByUri($uri, $rootZoneDatabase);

        return $rootZoneDatabase;
    }
}
