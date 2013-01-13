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
     * @var \Pdp\HttpAdapter\HttpAdapterInterface Http adapter interface
     */
    protected $httpAdapter;
    
    /**
     * @var string url
     */
    protected $publicSuffixListUrl = 'http://mxr.mozilla.org/mozilla-central/source/netwerk/dns/effective_tld_names.dat?raw=1';

	protected function setUp()
	{
        parent::setUp();

        $this->sourceFile = 'public_suffix_list.txt';
        $this->cacheFile = 'public_suffix_list.php';
        
		$this->root = vfsStream::setup('pdp');
		vfsStream::create(array('cache' => array()), $this->root);
		$this->cacheDir = vfsStream::url('pdp/cache');
        $this->httpAdapter = $this->getMock('\Pdp\HttpAdapter\HttpAdapterInterface');
        
        $this->listManager = new PublicSuffixListManager($this->cacheDir, $this->httpAdapter);
	}

	protected function tearDown()
	{
		$this->cacheDir = null;
		$this->root = null;
		$this->listManager = null;

        parent::tearDown();
	}

    /**
     * @group interwebs
     */
	public function testFetchListFromSource()
	{
        $content = file_get_contents(PDP_TEST_ROOT . '/_files/' . $this->sourceFile);
        
        $this->httpAdapter->expects($this->once())
            ->method('getContent')
            ->with($this->publicSuffixListUrl)
            ->will($this->returnValue($content));

        $publicSuffixList = $this->listManager->fetchListFromSource();
        $this->assertInternalType('string', $publicSuffixList);
        $this->assertGreaterThanOrEqual(100000, strlen($publicSuffixList));
	}

    /**
     * @group write-cache
     */
	public function testWritePhpCache()
	{
		$this->assertFalse(file_exists($this->cacheDir . '/' . $this->cacheFile));
        $array = $this->listManager->parseListToArray(
            PDP_TEST_ROOT . '/_files/' . $this->sourceFile
        );
        $this->assertGreaterThanOrEqual(230000, $this->listManager->writePhpCache($array));
		$this->assertFileExists($this->cacheDir . '/' . $this->cacheFile);
		$publicSuffixList = include $this->cacheDir . '/' . $this->cacheFile;
        $this->assertInternalType('array', $publicSuffixList);
        $this->assertGreaterThanOrEqual(300, count($publicSuffixList));
        $this->assertTrue(array_key_exists('stuff-4-sale', $publicSuffixList['org']) !== false);
        $this->assertTrue(array_key_exists('net', $publicSuffixList['ac']) !== false);
	}

    /**
     * @group parse-to-array
     */
    public function testParseListToArray()
    {
        $publicSuffixList = $this->listManager->parseListToArray(
            PDP_TEST_ROOT . '/_files/' . $this->sourceFile
        );
        $this->assertInternalType('array', $publicSuffixList);
    }

	public function testGetList()
	{
        copy(PDP_TEST_ROOT . '/_files/' . $this->cacheFile, 
            $this->cacheDir . '/' . $this->cacheFile);
		$this->assertFileExists($this->cacheDir . '/' . $this->cacheFile);
		$publicSuffixList = $this->listManager->getList();
        $this->assertInternalType('array', $publicSuffixList);
        $this->assertGreaterThanOrEqual(300, count($publicSuffixList));
        $this->assertTrue(array_key_exists('stuff-4-sale', $publicSuffixList['org']) !== false);
        $this->assertTrue(array_key_exists('net', $publicSuffixList['ac']) !== false);
	}

}

