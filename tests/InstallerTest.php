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

namespace Pdp\Tests;

use org\bovigo\vfs\vfsStream;
use Pdp\Cache;
use Pdp\HttpClient;
use Pdp\HttpClientException;
use Pdp\Installer;
use Pdp\Manager;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use function file_get_contents;
use function sprintf;

/**
 * @coversDefaultClass \Pdp\Installer
 */
class InstallerTest extends TestCase
{
    protected $cachePool;
    protected $cacheDir;
    protected $root;
    protected $client;
    protected $logger;

    public function setUp()
    {
        $this->root = vfsStream::setup('pdp');
        vfsStream::create(['cache' => []], $this->root);
        $this->cacheDir = vfsStream::url('pdp/cache');
        $this->cachePool = new Cache($this->cacheDir);
        $this->client = new class() implements HttpClient {
            public function getContent(string $url): string
            {
                if ($url === Manager::PSL_URL) {
                    return file_get_contents(__DIR__.'/data/public_suffix_list.dat');
                }

                if ($url === Manager::RZD_URL) {
                    return file_get_contents(__DIR__.'/data/tlds-alpha-by-domain.txt');
                }

                throw new HttpClientException(sprintf('invalid url: %s', $url));
            }
        };

        $this->logger = new class() extends AbstractLogger {
            private $data = [];

            public function log($level, $message, array $context = [])
            {
                $replace = [];
                foreach ($context as $key => $val) {
                    $replace['{'.$key.'}'] = $val;
                }

                $this->data[] = strtr($message, $replace);
            }

            public function getLogs(string $level = null): array
            {
                return $this->data;
            }
        };
    }

    public function tearDown()
    {
        $this->cachePool = null;
        $this->cacheDir = null;
        $this->root = null;
        $this->client = null;
        $this->logger = null;
    }

    /**
     * @dataProvider contextDataProvider
     * @param array $context
     * @param int   $retval
     * @param array $logs
     */
    public function testRefreshDefault(array $context, int $retval, array $logs)
    {
        $manager = new Manager($this->cachePool, $this->client);
        $installer = new Installer($manager, $this->logger);

        self::assertSame($retval, $installer->refresh($context));
        foreach ($logs as $log) {
            self::assertContains($log, $this->logger->getLogs());
        }
    }

    public function contextDataProvider(): array
    {
        return [
            'default' => [
                'context' =>[],
                'retval' => 0,
                'log' => [
                    'Public Suffix List Cache updated for 1 DAY using '.Manager::PSL_URL,
                    'IANA Root Zone Database Cache updated for 1 DAY using '.Manager::RZD_URL,
                ],
            ],
            'refresh psl only' => [
                'context' => [
                    Installer::REFRESH_PSL_KEY => true,
                ],
                'retval' => 0,
                'log' => [
                    'Public Suffix List Cache updated for 1 DAY using '.Manager::PSL_URL,
                ],
            ],
            'refresh tld only' => [
                'context' => [
                    Installer::REFRESH_RZD_KEY => true,
                ],
                'retval' => 0,
                'log' => [
                    'IANA Root Zone Database Cache updated for 1 DAY using '.Manager::RZD_URL,
                ],
            ],
            'refresh psl fails' => [
                'context' => [
                    Installer::REFRESH_PSL_KEY => true,
                    Installer::REFRESH_PSL_URL_KEY => 'http://localhost/',
                ],
                'retval' => 1,
                'log' => [
                    'Local cache update failed with invalid url: http://localhost/',
                ],
            ],
        ];
    }
}
