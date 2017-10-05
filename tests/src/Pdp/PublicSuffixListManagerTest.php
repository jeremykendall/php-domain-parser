<?php

namespace Pdp;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Pdp\HttpAdapter\CurlHttpAdapter;
use Pdp\HttpAdapter\HttpAdapterInterface;
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
     * @var string url
     */
    protected $publicSuffixListUrl = 'https://publicsuffix.org/list/effective_tld_names.dat';

    /**
     * @var HttpAdapterInterface|\PHPUnit_Framework_MockObject_MockObject Http adapter
     */
    protected $httpAdapter;

    protected function setUp()
    {
        parent::setUp();

        $this->dataDir = dirname(__DIR__, 3) . '/data';

        $this->root = vfsStream::setup('pdp');
        vfsStream::create(['cache' => []], $this->root);
        $this->cacheDir = vfsStream::url('pdp/cache');

        $this->listManager = new PublicSuffixListManager($this->cacheDir);

        $this->httpAdapter = $this->getMock(HttpAdapterInterface::class);
        $this->listManager->setHttpAdapter($this->httpAdapter);
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
            $this->dataDir . '/' . PublicSuffixListManager::PDP_PSL_TEXT_FILE
        );

        $this->httpAdapter->expects($this->once())
            ->method('getContent')
            ->with($this->publicSuffixListUrl)
            ->will($this->returnValue($content));

        $this->assertFileNotExists(
            $this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_TEXT_FILE
        );
        $this->assertFileNotExists(
            $this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE
        );

        $this->listManager->refreshPublicSuffixList();

        $this->assertFileExists(
            $this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_TEXT_FILE
        );
        $this->assertFileExists(
            $this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE
        );
    }

    public function testFetchListFromSource()
    {
        $content = file_get_contents(
            $this->dataDir . '/' . PublicSuffixListManager::PDP_PSL_TEXT_FILE
        );

        $this->httpAdapter->expects($this->once())
            ->method('getContent')
            ->with($this->publicSuffixListUrl)
            ->will($this->returnValue($content));

        $publicSuffixList = $this->listManager->fetchListFromSource();
        $this->assertGreaterThanOrEqual(100000, $publicSuffixList);
    }

    public function testGetHttpAdapterReturnsDefaultCurlAdapterIfAdapterNotSet()
    {
        $listManager = new PublicSuffixListManager($this->cacheDir);
        $this->assertInstanceOf(CurlHttpAdapter::class, $listManager->getHttpAdapter());
    }

    public function testWritePhpCache()
    {
        $this->assertFileNotExists(
            $this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE
        );
        $array = $this->listManager->parseListToArray(
            $this->dataDir . '/' . PublicSuffixListManager::PDP_PSL_TEXT_FILE
        );
        $this->assertGreaterThanOrEqual(230000, $this->listManager->writePhpCache($array));
    }

    public function testWriteThrowsExceptionIfCanNotWrite()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Cannot write '/does/not/exist/public-suffix-list.php'");
        $manager = new PublicSuffixListManager('/does/not/exist');
        $manager->writePhpCache([]);
    }

    public function testParseListToArray()
    {
        $publicSuffixList = $this->listManager->parseListToArray(
            $this->dataDir . '/' . PublicSuffixListManager::PDP_PSL_TEXT_FILE
        );
        $this->assertInternalType('array', $publicSuffixList);
    }

    public function testGetList()
    {
        copy(
            $this->dataDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE,
            $this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE
        );
        $this->assertFileExists(
            $this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE
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
            $this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE
        );

        /** @var PublicSuffixListManager|\PHPUnit_Framework_MockObject_MockObject $listManager */
        $listManager = $this->getMockBuilder(PublicSuffixListManager::class)
            ->setConstructorArgs([$this->cacheDir])
            ->setMethods(['refreshPublicSuffixList'])
            ->getMock();

        $dataDir = $this->dataDir;
        $cacheDir = $this->cacheDir;

        $listManager->expects($this->once())
            ->method('refreshPublicSuffixList')
            ->will($this->returnCallback(function () use ($dataDir, $cacheDir) {
                copy(
                    $dataDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE,
                    $cacheDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE
                );
            }));

        $publicSuffixList = $listManager->getList();
        $this->assertInstanceOf(PublicSuffixList::class, $publicSuffixList);
    }

    public function testGetProvidedListFromDefaultCacheDir()
    {
        // By not providing cache I'm forcing use of default cache dir
        $listManager = new PublicSuffixListManager();
        $publicSuffixList = $listManager->getList();
        $this->assertGreaterThanOrEqual(300, count($publicSuffixList->getRules()));
    }

    private function getMock(string $class)
    {
        return $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
