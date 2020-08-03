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

use Composer\IO\BaseIO;
use Composer\Script\Event;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheException as PsrCacheException;
use Throwable;
use function array_replace;
use function extension_loaded;
use function file_exists;
use function filter_var_array;
use function fwrite;
use function implode;
use const FILTER_FLAG_STRIP_LOW;
use const FILTER_SANITIZE_STRING;
use const FILTER_VALIDATE_BOOLEAN;
use const PHP_EOL;
use const STDERR;
use const STDOUT;

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
     * Creates a new instance with a Pdp\Cache object and the cURL HTTP client.
     * @param LoggerInterface $logger
     * @param string          $cacheDir
     */
    public static function createFromCacheDir(LoggerInterface $logger, string $cacheDir = ''): self
    {
        return new self(new Manager(new Cache($cacheDir), new CurlHttpClient()), $logger);
    }

    /**
     * Refresh the locale cache.
     * @param array $context
     */
    public function refresh(array $context = []): bool
    {
        $context = filter_var_array(array_replace(self::DEFAULT_CONTEXT, $context), [
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
            return $this->execute($context);
        } catch (PsrCacheException $exception) {
            $this->logger->error('Local cache update failed with {exception}', ['exception' => $exception->getMessage()]);
            return false;
        } catch (Throwable $exception) {
            $this->logger->error('Local cache update failed with {exception}', ['exception' => $exception->getMessage()]);
            return false;
        }
    }

    /**
     * Refreshes the cache.
     * @param array $arguments
     */
    private function execute(array $arguments = []): bool
    {
        if ($arguments[self::REFRESH_PSL_KEY]) {
            if (!$this->manager->refreshRules($arguments[self::REFRESH_PSL_URL_KEY], $arguments[self::TTL_KEY])) {
                $this->logger->error('Unable to update the Public Suffix List Cache using {psl_url} with a TTL of {ttl}', [
                    'psl_url' => $arguments[self::REFRESH_PSL_URL_KEY],
                    'ttl' => $arguments[self::TTL_KEY],
                ]);

                return false;
            }

            $this->logger->info('Public Suffix List Cache updated for {ttl} using {psl_url}', [
                'psl_url' => $arguments[self::REFRESH_PSL_URL_KEY],
                'ttl' => $arguments[self::TTL_KEY],
            ]);
        }

        if (!$arguments[self::REFRESH_RZD_KEY]) {
            return true;
        }

        $this->logger->info('Updating your IANA Root Zone Database copy.');
        if ($this->manager->refreshTLDs($arguments[self::REFRESH_RZD_URL_KEY], $arguments[self::TTL_KEY])) {
            $this->logger->info('IANA Root Zone Database Cache updated for {ttl} using {rzd_url}', [
                'rzd_url' => $arguments[self::REFRESH_RZD_URL_KEY],
                'ttl' => $arguments[self::TTL_KEY],
            ]);

            return true;
        }

        $this->logger->error('Unable to update the IANA Root Zone Database Cache using {rzd_url} with a TTL of {ttl}', [
            'rzd_url' => $arguments[self::REFRESH_RZD_URL_KEY],
            'ttl' => $arguments[self::TTL_KEY],
        ]);

        return false;
    }

    /**
     * Script to update the local cache using composer hook.
     * @param null|Event $event
     */
    public static function updateLocalCache(Event $event = null): void
    {
        $io = self::getIO($event);
        if (!extension_loaded('curl')) {
            $io->writeError('The required PHP cURL extension is missing.');

            die(1);
        }

        $vendor = self::getVendorPath($event);
        if (null === $vendor) {
            $io->writeError([
                'You must set up the project dependencies using composer',
                'see https://getcomposer.org',
            ]);

            die(1);
        }

        require $vendor.'/autoload.php';

        $logger = new Logger();
        $arguments = [
            self::CACHE_DIR_KEY => '',
            self::REFRESH_PSL_KEY => false,
            self::REFRESH_RZD_KEY => false,
            self::TTL_KEY => '1 DAY',
        ];

        if (null !== $event) {
            /** @var BaseIO $logger */
            $logger = $event->getIO();
            $arguments = array_replace($arguments, $event->getArguments());
        }

        $installer = self::createFromCacheDir($logger, $arguments[Installer::CACHE_DIR_KEY]);
        $io->write('Updating your Pdp local cache.');
        if ($installer->refresh($arguments)) {
            $io->write('Pdp local cache successfully updated.');
            die(0);
        }

        $io->writeError('The command failed to update Pdp local cache.');
        die(1);
    }

    /**
     * Detect the I/O instance to use.
     *
     * @param Event|null $event
     *
     * @return mixed
     */
    private static function getIO(Event $event = null)
    {
        return null !== $event ? $event->getIO() : new class() {
            /**
             * @param string|string[] $messages
             * @param bool            $newline
             * @param int             $verbosity
             */
            public function write($messages, bool $newline = true, int $verbosity = 2): void
            {
                $this->doWrite($messages, $newline, false, $verbosity);
            }

            /**
             * @param string|string[] $messages
             * @param bool            $newline
             * @param int             $verbosity
             */
            public function writeError($messages, bool $newline = true, int $verbosity = 2): void
            {
                $this->doWrite($messages, $newline, true, $verbosity);
            }

            /**
             * @param string|string[] $messages
             * @param bool            $newline
             * @param bool            $stderr
             * @param int             $verbosity
             */
            private function doWrite($messages, bool $newline, bool $stderr, int $verbosity): void
            {
                fwrite(
                    $stderr ? STDERR : STDOUT,
                    implode($newline ? PHP_EOL : '', (array) $messages).PHP_EOL
                );
            }
        };
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
