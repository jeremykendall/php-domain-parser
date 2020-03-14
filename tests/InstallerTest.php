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
use Pdp\Logger;
use Pdp\Manager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use function file_get_contents;
use function rewind;
use function sprintf;
use function stream_get_contents;

/**
 * @coversDefaultClass \Pdp\Installer
 */
class InstallerTest extends TestCase
{
    /**
     * @var Cache
     */
    protected $cachePool;

    /**
     * @var string
     */
    protected $cacheDir;

    /**
     * @var \org\bovigo\vfs\vfsStreamDirectory
     */
    protected $root;

    /**
     * @var HttpClient
     */
    protected $client;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @return resource
     */
    private function setStream()
    {
        /** @var resource $stream */
        $stream = fopen('php://memory', 'r+');
        return $stream;
    }

    public function setUp(): void
    {
        $this->root = vfsStream::setup('pdp');
        vfsStream::create(['cache' => []], $this->root);
        $this->cacheDir = vfsStream::url('pdp/cache');
        $this->cachePool = new Cache($this->cacheDir);
        $this->client = new class() implements HttpClient {
            public function getContent(string $url): string
            {
                if ($url === Manager::PSL_URL) {
                    /** @var string $res */
                    $res = file_get_contents(__DIR__.'/data/public_suffix_list.dat');

                    return $res;
                }

                if ($url === Manager::RZD_URL) {
                    /** @var string $res */
                    $res = file_get_contents(__DIR__.'/data/tlds-alpha-by-domain.txt');

                    return $res;
                }

                throw new HttpClientException(sprintf('invalid url: %s', $url));
            }
        };
    }

    public function tearDown(): void
    {
        unset($this->cachePool, $this->cacheDir, $this->root, $this->client, $this->logger);
    }

    /**
     * @dataProvider contextDataProvider
     * @param array $context
     * @param bool  $retval
     * @param array $logs
     */
    public function testRefreshDefault(array $context, bool $retval, array $logs): void
    {
        $stream = $this->setStream();
        $logger = new Logger($stream, $stream);
        $manager = new Manager($this->cachePool, $this->client);
        $installer = new Installer($manager, $logger);
        self::assertSame($retval, $installer->refresh($context));
        rewind($stream);
        /** @var string $data */
        $data = stream_get_contents($stream);
        foreach ($logs as $log) {
            self::assertStringContainsString($log, $data);
        }
    }

    public function contextDataProvider(): array
    {
        return [
            'default' => [
                'context' =>[],
                'retval' => true,
                'log' => [
                    'Public Suffix List Cache updated for 1 DAY using '.Manager::PSL_URL,
                    'IANA Root Zone Database Cache updated for 1 DAY using '.Manager::RZD_URL,
                ],
            ],
            'refresh psl only' => [
                'context' => [
                    Installer::REFRESH_PSL_KEY => true,
                ],
                'retval' => true,
                'log' => [
                    'Public Suffix List Cache updated for 1 DAY using '.Manager::PSL_URL,
                ],
            ],
            'refresh tld only' => [
                'context' => [
                    Installer::REFRESH_RZD_KEY => true,
                ],
                'retval' => true,
                'log' => [
                    'IANA Root Zone Database Cache updated for 1 DAY using '.Manager::RZD_URL,
                ],
            ],
            'refresh psl fails' => [
                'context' => [
                    Installer::REFRESH_PSL_KEY => true,
                    Installer::REFRESH_PSL_URL_KEY => 'http://localhost/',
                ],
                'retval' => false,
                'log' => [
                    'Local cache update failed with invalid url: http://localhost/',
                ],
            ],
        ];
    }
}
