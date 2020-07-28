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

use Pdp\Rules;
use Pdp\Storage\Cache\RulesCache;
use Pdp\Storage\Cache\TopLevelDomainsCache;
use Pdp\Storage\Http\Client;
use Pdp\TopLevelDomains;
use Pdp\UnableToLoadRules;
use Pdp\UnableToLoadTopLevelDomains;

/**
 * Public Suffix List Manager.
 *
 * This class obtains, writes, caches, and returns PHP representations
 * of the Public Suffix List ICANN section
 *
 * @author Jeremy Kendall <jeremy@jeremykendall.net>
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class Manager
{
    public const PSL_URL = 'https://publicsuffix.org/list/public_suffix_list.dat';
    private const RZD_URL = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';

    private Client $http;

    private RulesCache $rulesCache;

    private TopLevelDomainsCache $topLevelDomainsCache;

    public function __construct(Client $http, RulesCache $rulesCache, TopLevelDomainsCache $topLevelDomainsCache)
    {
        $this->http = $http;
        $this->rulesCache = $rulesCache;
        $this->topLevelDomainsCache = $topLevelDomainsCache;
    }

    /**
     * Gets the Public Suffix List from the Local Storage or the Remote Storage.
     *
     * @throws UnableToLoadRules
     */
    public function getRulesLocalCopy(string $url = self::PSL_URL): Rules
    {
        return $this->rulesCache->fetchByUri($url) ?? $this->getRulesRemoteCopy($url);
    }

    /**
     * Gets the Public Suffix List from an the Remote Storage.
     *
     * @throws UnableToLoadRules
     */
    public function getRulesRemoteCopy(string $uri = null): Rules
    {
        $uri = $uri ?? self::PSL_URL;
        $rules = Rules::createFromString($this->http->getContent($uri));

        $this->rulesCache->storeByUri($uri, $rules);

        return $rules;
    }

    /**
     * Gets the Top Level Domains from the Local Storage or the Remote Storage.
     *
     * @throws UnableToLoadTopLevelDomains
     */
    public function getTopLevelDomainsLocalCopy(string $uri = null): TopLevelDomains
    {
        $uri = $uri ?? self::RZD_URL;

        return $this->topLevelDomainsCache->fetchByUri($uri) ?? $this->getTopLevelDomainsRemoteCopy($uri);
    }

    /**
     * Gets the Top Level Domains from the Remote Storage.
     *
     * @throws UnableToLoadTopLevelDomains
     */
    public function getTopLevelDomainsRemoteCopy(string $uri = null): TopLevelDomains
    {
        $uri = $uri ?? self::RZD_URL;
        $topLevelDomains = TopLevelDomains::createFromString($this->http->getContent($uri));

        $this->topLevelDomainsCache->storeByUri($uri, $topLevelDomains);

        return $topLevelDomains;
    }
}
