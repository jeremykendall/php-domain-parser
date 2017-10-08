<?php

declare(strict_types=1);

namespace Pdp\Tests;

use Exception;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Pdp\Http\CurlHttpAdapter;
use Pdp\Http\HttpAdapter;
use Pdp\PublicSuffixList;
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
        parent::setUp();

        $this->dataDir = dirname(__DIR__) . '/data';

        $this->root = vfsStream::setup('pdp');
        vfsStream::create(['cache' => []], $this->root);
        $this->cacheDir = vfsStream::url('pdp/cache');

        $this->httpAdapter = $this->getMock(HttpAdapter::class);

        $this->listManager = new PublicSuffixListManager($this->httpAdapter, $this->cacheDir);
    }

    protected function tearDown()
    {
        $this->cacheDir = null;
        $this->root = null;
        $this->httpAdapter = null;
        $this->listManager = null;

        parent::tearDown();
    }

    public function testRefreshPublicSuffixList()
    {
        $content = file_get_contents(
            $this->dataDir . '/' . PublicSuffixListManager::PUBLIC_SUFFIX_LIST_RAW
        );

        $this->httpAdapter->expects($this->once())
            ->method('getContent')
            ->with(PublicSuffixListManager::PUBLIC_SUFFIX_LIST_URL)
            ->will($this->returnValue($content));

        $this->assertFileNotExists(
            $this->cacheDir . '/' . PublicSuffixListManager::PUBLIC_SUFFIX_LIST_RAW
        );
        $this->assertFileNotExists(
            $this->cacheDir . '/' . PublicSuffixListManager::PUBLIC_SUFFIX_LIST_JSON
        );

        $this->listManager->refreshPublicSuffixList();

        $this->assertFileExists(
            $this->cacheDir . '/' . PublicSuffixListManager::PUBLIC_SUFFIX_LIST_RAW
        );
        $this->assertFileExists(
            $this->cacheDir . '/' . PublicSuffixListManager::PUBLIC_SUFFIX_LIST_JSON
        );
    }

    public function testWriteThrowsExceptionIfCanNotWrite()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot write '/does/not/exist/public-suffix-list.txt'");
        $manager = new PublicSuffixListManager(new CurlHttpAdapter(), '/does/not/exist');
        $manager->refreshPublicSuffixList();
    }

    public function testGetList()
    {
        copy(
            $this->dataDir . '/' . PublicSuffixListManager::PUBLIC_SUFFIX_LIST_JSON,
            $this->cacheDir . '/' . PublicSuffixListManager::PUBLIC_SUFFIX_LIST_JSON
        );
        $this->assertFileExists(
            $this->cacheDir . '/' . PublicSuffixListManager::PUBLIC_SUFFIX_LIST_JSON
        );
        $publicSuffixList = $this->listManager->getList();
        $this->assertInstanceOf(PublicSuffixList::class, $publicSuffixList);
        $this->assertGreaterThanOrEqual(300, count($publicSuffixList->getRules()));
        $this->assertArrayHasKey('stuff-4-sale', $publicSuffixList->getRules()['org']);
        $this->assertArrayHasKey('net', $publicSuffixList->getRules()['ac']);
    }

    public function testGetListWithoutCache()
    {
        $this->assertFileNotExists(
            $this->cacheDir . '/' . PublicSuffixListManager::PUBLIC_SUFFIX_LIST_JSON
        );

        /** @var PublicSuffixListManager|\PHPUnit_Framework_MockObject_MockObject $listManager */
        $listManager = $this->getMockBuilder(PublicSuffixListManager::class)
            ->setConstructorArgs([$this->httpAdapter, $this->cacheDir])
            ->setMethods(['refreshPublicSuffixList'])
            ->getMock();

        $listManager->expects($this->once())
            ->method('refreshPublicSuffixList')
            ->will($this->returnCallback(function () {
                copy(
                    $this->dataDir . '/' . PublicSuffixListManager::PUBLIC_SUFFIX_LIST_JSON,
                    $this->cacheDir . '/' . PublicSuffixListManager::PUBLIC_SUFFIX_LIST_JSON
                );
            }));

        $publicSuffixList = $listManager->getList();
        $this->assertInstanceOf(PublicSuffixList::class, $publicSuffixList);
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
}
