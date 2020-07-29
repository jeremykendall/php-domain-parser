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

use Pdp\Storage\Cache\Psr16FileCache;
use Pdp\Storage\Cache\RulesCachePsr16Adapter;
use Pdp\Storage\Cache\TopLevelDomainsCachePsr16Adapter;
use Pdp\Storage\Http\CurlClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use function filter_var_array;
use const FILTER_FLAG_STRIP_LOW;
use const FILTER_SANITIZE_STRING;

/**
 * A class to install and update local cache.
 *
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class Installer
{
    public const CACHE_DIR_KEY = 'cache-dir';
    public const URI_KEY_PSL = 'psl';
    public const URI_KEY_RZD = 'rzd';
    public const TTL_KEY = 'ttl';
    public const DEFAULT_CONTEXT = [
        self::CACHE_DIR_KEY => '',
        self::URI_KEY_PSL => null,
        self::URI_KEY_RZD => null,
        self::TTL_KEY => '1 DAY',
    ];

    public const DEFAULT_REMOTE_URI = [
        self::URI_KEY_PSL => 'https://publicsuffix.org/list/public_suffix_list.dat',
        self::URI_KEY_RZD => 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt',
    ];

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Refresh the locale cache.
     */
    public function updateLocalCache(array $arguments = []): bool
    {
        $context = $arguments + self::DEFAULT_CONTEXT;
        $context = filter_var_array($context, [
            self::URI_KEY_PSL => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW, 'default' => self::DEFAULT_REMOTE_URI[self::URI_KEY_PSL]],
            self::URI_KEY_RZD => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW, 'default' => self::DEFAULT_REMOTE_URI[self::URI_KEY_RZD]],
            self::TTL_KEY => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW, 'default' => '1 DAY'],
        ]);

        try {
            $psr16Cache = new Psr16FileCache($context[self::CACHE_DIR_KEY]);
            $storage = new Manager(
                new CurlClient(),
                new RulesCachePsr16Adapter($psr16Cache, $context[self::TTL_KEY], $this->logger),
                new TopLevelDomainsCachePsr16Adapter($psr16Cache, $context[self::TTL_KEY], $this->logger),
            );

            $storage->getPublicSuffixListRemoteCopy($context[self::URI_KEY_PSL]);
            $storage->getRootZoneDatabaseRemoteCopy($context[self::URI_KEY_PSL]);
        } catch (Throwable $exception) {
            $this->logger->error('Local cache update failed with {exception}', ['exception' => $exception->getMessage()]);

            return false;
        }

        return true;
    }
}
