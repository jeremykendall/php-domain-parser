<?php

namespace Pdp;

use org\bovigo\vfs\vfsStream;

class PublicSuffixListManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PublicSuffixListManager List manager
     */
	protected $listManager;

	/**
	 * @var  vfsStreamDirectory
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
     * @var string url
     */
    protected $publicSuffixListUrl = 'http://mxr.mozilla.org/mozilla-central/source/netwerk/dns/effective_tld_names.dat?raw=1';

	protected function setUp()
	{
        parent::setUp();

		$this->root = vfsStream::setup('pdp');
		vfsStream::create(array('cache' => array()), $this->root);
		$this->cacheDir = vfsStream::url('pdp/cache');
        
        $this->listManager = new PublicSuffixListManager($this->cacheDir);
	}

	protected function tearDown()
	{
		$this->cacheDir = null;
		$this->root = null;
		$this->listManager = null;

        parent::tearDown();
	}

    public function testRefreshPublicSuffixList()
    {
        $content = file_get_contents(PDP_TEST_ROOT . '/_files/' . PublicSuffixListManager::PDP_PSL_TEXT_FILE);
        
        $httpAdapter = $this->getMock('\Pdp\HttpAdapter\HttpAdapterInterface');
        $httpAdapter->expects($this->once())
            ->method('getContent')
            ->with($this->publicSuffixListUrl)
            ->will($this->returnValue($content));

        $this->assertFalse(file_exists($this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_TEXT_FILE));
        $this->assertFalse(file_exists($this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE));

        $this->listManager->refreshPublicSuffixList($httpAdapter);

        $this->assertFileExists($this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_TEXT_FILE);
        $this->assertFileExists($this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE);
    }
        

	public function testFetchListFromSource()
	{
        $content = file_get_contents(PDP_TEST_ROOT . '/_files/' . PublicSuffixListManager::PDP_PSL_TEXT_FILE);
        
        $httpAdapter = $this->getMock('\Pdp\HttpAdapter\HttpAdapterInterface');
        $httpAdapter->expects($this->once())
            ->method('getContent')
            ->with($this->publicSuffixListUrl)
            ->will($this->returnValue($content));

        $publicSuffixList = $this->listManager->fetchListFromSource($httpAdapter);
        $this->assertGreaterThanOrEqual(100000, $publicSuffixList);
	}

	public function testWritePhpCache()
	{
		$this->assertFalse(file_exists($this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE));
        $array = $this->listManager->parseListToArray(
            PDP_TEST_ROOT . '/_files/' . PublicSuffixListManager::PDP_PSL_TEXT_FILE
        );
        $this->assertGreaterThanOrEqual(230000, $this->listManager->writePhpCache($array));
		$this->assertFileExists($this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE);
		$publicSuffixList = include $this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE;
        $this->assertInternalType('array', $publicSuffixList);
        $this->assertGreaterThanOrEqual(300, count($publicSuffixList));
        $this->assertTrue(array_key_exists('stuff-4-sale', $publicSuffixList['org']) !== false);
        $this->assertTrue(array_key_exists('net', $publicSuffixList['ac']) !== false);
	}

    public function testWriteThrowsExceptionIfCanNotWrite()
    {
        $this->setExpectedException('\Exception', "Cannot write '/does/not/exist/public-suffix-list.php'");
        $manager = new PublicSuffixListManager('/does/not/exist');
        $manager->writePhpCache(array());
    }

    public function testParseListToArray()
    {
        $publicSuffixList = $this->listManager->parseListToArray(
            PDP_TEST_ROOT . '/_files/' . PublicSuffixListManager::PDP_PSL_TEXT_FILE
        );
        $this->assertInternalType('array', $publicSuffixList);
    }

	public function testGetList()
	{
        copy(PDP_TEST_ROOT . '/_files/' . PublicSuffixListManager::PDP_PSL_PHP_FILE, 
            $this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE);
		$this->assertFileExists($this->cacheDir . '/' . PublicSuffixListManager::PDP_PSL_PHP_FILE);
		$publicSuffixList = $this->listManager->getList();
        $this->assertInstanceOf('\Pdp\PublicSuffixList', $publicSuffixList);
        $this->assertGreaterThanOrEqual(300, count($publicSuffixList));
        $this->assertTrue(array_key_exists('stuff-4-sale', $publicSuffixList['org']) !== false);
        $this->assertTrue(array_key_exists('net', $publicSuffixList['ac']) !== false);
	}

}

