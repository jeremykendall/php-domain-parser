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

namespace Pdp;

use Composer\Script\Event;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheException as PsrCacheException;
use Throwable;
use function array_merge;
use function extension_loaded;
use function file_exists;
use function filter_var_array;
use function fwrite;
use const FILTER_FLAG_STRIP_LOW;
use const FILTER_SANITIZE_STRING;
use const FILTER_VALIDATE_BOOLEAN;
use const STDERR;

/**
 * A class to install and update local cache.
 *
 * @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
final class Installer
{
    const CACHE_DIR_KEY = 'cache-dir';
    const REFRESH_PSL_KEY = 'psl';
    const REFRESH_PSL_URL_KEY = 'psl-url';
    const REFRESH_RZD_KEY = 'rzd';
    const REFRESH_RZD_URL_KEY = 'rzd-url';
    const TTL_KEY = 'ttl';

    const DEFAULT_CONTEXT = [
        self::CACHE_DIR_KEY => '',
        self::REFRESH_PSL_KEY => false,
        self::REFRESH_PSL_URL_KEY => Manager::PSL_URL,
        self::REFRESH_RZD_KEY => false,
        self::REFRESH_RZD_URL_KEY => Manager::RZD_URL,
        self::TTL_KEY => '1 DAY',
    ];

    /**
     * @var Manager
     */
    private $manager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Manager $manager, LoggerInterface $logger)
    {
        $this->manager = $manager;
        $this->logger = $logger;
    }

    /**
     * Creates a new installer instance with a Pdp\Cache.
     * @param LoggerInterface $logger
     * @param string          $cacheDir
     */
    public static function createFromCacheDir(LoggerInterface $logger, string $cacheDir = ''): self
    {
        return new self(new Manager(new Cache($cacheDir), new CurlHttpClient()), $logger);
    }

    public function refresh(array $context = []): int
    {
        $context = filter_var_array(array_merge(self::DEFAULT_CONTEXT, $context), [
            self::REFRESH_PSL_KEY => FILTER_VALIDATE_BOOLEAN,
            self::REFRESH_PSL_URL_KEY => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW, 'default' => Manager::PSL_URL],
            self::REFRESH_RZD_KEY => FILTER_VALIDATE_BOOLEAN,
            self::REFRESH_RZD_URL_KEY => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW, 'default' => Manager::RZD_URL],
            self::TTL_KEY => ['filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW, 'default' => '1 DAY'],
        ]);

        if (false === $context[self::REFRESH_RZD_KEY] && false === $context[self::REFRESH_PSL_KEY]) {
            $context[self::REFRESH_PSL_KEY] = true;
            $context[self::REFRESH_RZD_KEY] = true;
        }

        try {
            $retVal = $this->execute($context);
        } catch (PsrCacheException $exception) {
            $this->logger->error('😓 😓 😓 Your local cache could not be updated. 😓 😓 😓');
            $this->logger->error('An error occurred during cache regeneration.');
            $this->logger->error('----- Error Message ----');
            $this->logger->error($exception->getMessage());
            $retVal = 1;
        } catch (Throwable $exception) {
            $this->logger->error('😓 😓 😓 Your local cache could not be updated. 😓 😓 😓');
            $this->logger->error('An error occurred during the update.');
            $this->logger->error('----- Error Message ----');
            $this->logger->error($exception->getMessage());
            $retVal = 1;
        }

        return $retVal;
    }

    /**
     * Refreshes the cache.
     *
     * @param array $arguments
     *
     * @throws PsrCacheException
     */
    private function execute(array $arguments = []): int
    {
        $this->logger->info('Updating your Pdp local cache.');

        if ($arguments[self::REFRESH_PSL_KEY]) {
            $this->logger->info('Updating your Public Suffix List copy.');
            if (!$this->manager->refreshRules($arguments[self::REFRESH_PSL_URL_KEY], $arguments[self::TTL_KEY])) {
                $this->logger->error('😓 😓 😓 Your Public Suffix List copy could not be updated. 😓 😓 😓');
                $this->logger->error('Please review your settings.');

                return 1;
            }

            $this->logger->info('💪 💪 💪 Your Public Suffix List copy is updated. 💪 💪 💪');
        }

        if (!$arguments[self::REFRESH_RZD_KEY]) {
            return 0;
        }

        $this->logger->info('Updating your IANA Root Zone Database copy.');
        if ($this->manager->refreshTLDs($arguments[self::REFRESH_RZD_URL_KEY], $arguments[self::TTL_KEY])) {
            $this->logger->info('💪 💪 💪 Your IANA Root Zone Database copy is updated. 💪 💪 💪');

            return 0;
        }

        $this->logger->error('😓 😓 😓 Your IANA Root Zone Database copy could not be updated. 😓 😓 😓');
        $this->logger->error('Please review your settings.');

        return 1;
    }

    /**
     * Script to update the local cache using composer hook.
     *
     * @param Event $event
     */
    public static function updateLocalCache(Event $event = null)
    {
        if (!extension_loaded('curl')) {
            fwrite(STDERR, 'The PHP cURL extension is missing.');

            die(1);
        }

        $vendor = self::getVendorPath($event);
        if (null === $vendor) {
            fwrite(STDERR, implode(PHP_EOL, [
                'You must set up the project dependencies using composer',
                'see https://getcomposer.org',
            ]).PHP_EOL);
            die(1);
        }

        require $vendor.'/autoload.php';

        $arguments = [
            self::CACHE_DIR_KEY => '',
            self::REFRESH_PSL_KEY => false,
            self::REFRESH_RZD_KEY => false,
            self::TTL_KEY => '1 DAY',
        ];

        if (null !== $event) {
            $arguments = array_replace($arguments, $event->getArguments());
        }

        $installer = self::createFromCacheDir(new Logger(), $arguments[Installer::CACHE_DIR_KEY]);
        $retVal = $installer->refresh($arguments);

        die($retVal);
    }

    /**
     * Detect the vendor path.
     *
     * @param Event|null $event
     *
     * @return string|null
     */
    private static function getVendorPath(Event $event = null)
    {
        if (null !== $event) {
            return $event->getComposer()->getConfig()->get('vendor-dir');
        }

        for ($i = 1; $i <= 5; $i++) {
            if (is_dir($vendor = dirname(__DIR__, $i).'/vendor') && file_exists($vendor.'/autoload.php')) {
                return $vendor;
            }
        }

        return null;
    }
}
