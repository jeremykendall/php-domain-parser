<?php

declare(strict_types=1);

namespace Pdp\Tests;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Pdp\Cache\FileCache;
use Pdp\Http\HttpAdapter;
use Pdp\PublicSuffixListManager;
use PHPUnit\Framework\TestCase;

class PublicSuffixListManagerTest extends TestCase
{
    /**
     * @var PublicSuffixListManager List manager
     */
    protected $listManager;

    /**
     * @var vfsStreamDirectory
     */
    protected $root;

    /**
     * @var string Cache directory
     */
    protected $cacheDir;

    /**
     * @var string Source file name
     */
    protected $sourceFile;

    /**
     * @var string Cache file name
     */
    protected $cacheFile;

    /**
     * @var string data dir
     */
    protected $dataDir;

    /**
     * @var HttpAdapter|\PHPUnit_Framework_MockObject_MockObject Http adapter
     */
    protected $httpAdapter;

    protected function setUp()
    {
        $this->dataDir = dirname(__DIR__) . '/data';
        $this->root = vfsStream::setup('pdp');
        vfsStream::create(['cache' => []], $this->root);
        $this->cacheDir = vfsStream::url('pdp/cache');
        $this->httpAdapter = $this->getMock(HttpAdapter::class);
        $this->listManager = new PublicSuffixListManager($this->httpAdapter, new FileCache($this->cacheDir));
    }

    protected function tearDown()
    {
        $this->cacheDir = null;
        $this->root = null;
        $this->httpAdapter = null;
        $this->listManager = null;
    }

    public function testGetProvidedListFromDefaultCacheDir()
    {
        // By not providing cache I'm forcing use of default cache dir
        $listManager = new PublicSuffixListManager($this->httpAdapter);
        $publicSuffixList = $listManager->getList();
        $this->assertGreaterThanOrEqual(300, count($publicSuffixList->getRules()));
    }

    private function getMock(string $class)
    {
        return $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testGetDifferentPublicList()
    {
        $listManager = new PublicSuffixListManager($this->httpAdapter);
        $publicList = $listManager->getList();
        $invalidList = $listManager->getList('invalid type');
        $this->assertEquals($publicList, $invalidList);
    }

    public function testRefreshList()
    {
        $listManager = new PublicSuffixListManager($this->httpAdapter);
        $previous = $listManager->getList();
        $listManager->refreshPublicSuffixList();
        $this->assertEquals($previous, $listManager->getList());
    }
}
