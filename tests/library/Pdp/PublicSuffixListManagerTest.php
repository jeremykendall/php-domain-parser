<?php

namespace Pdp;

use org\bovigo\vfs\vfsStream;

class PublicSuffixListManagerTest extends \PHPUnit_Framework_TestCase
{
	protected $listManager;

	/**
	 * @var  vfsStreamDirectory
	 */
	protected $root;

	protected $cacheDir;

	protected function setUp()
	{
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
	}

	public function testFetchListFromSource()
	{
		$text = $this->listManager->fetchListFromSource();
		$this->assertFileExists($this->cacheDir . '/public_suffix_list.txt');
		$this->assertGreaterThanOrEqual(100000, filesize($this->cacheDir . '/public_suffix_list.txt'));
	}

	public function testWritePhpCache()
	{
		$this->assertFalse(file_exists($this->cacheDir . '/public_suffix_list.php'));
        $this->assertTrue($this->listManager->writePhpCache(PDP_TEST_ROOT . '/_files/public_suffix_list.txt'));
		$this->assertFileExists($this->cacheDir . '/public_suffix_list.php');
		$publicSuffixList = include $this->cacheDir . '/public_suffix_list.php';
        $this->assertInternalType('array', $publicSuffixList);
        $this->assertGreaterThanOrEqual(6000, count($publicSuffixList));
        $this->assertTrue(array_search('stuff-4-sale.org', $publicSuffixList) !== false);
        $this->assertTrue(array_search('net.ac', $publicSuffixList) !== false);
	}

	/**
	 * @group parse
	 */
	public function testParseListToArray()
	{
        $publicSuffixList = $this->listManager->parseListToArray(PDP_TEST_ROOT . '/_files/public_suffix_list.txt');
        $this->assertInternalType('array', $publicSuffixList);
        $this->assertGreaterThanOrEqual(6000, count($publicSuffixList));
        $this->assertTrue(array_search('stuff-4-sale.org', $publicSuffixList) !== false);
        $this->assertTrue(array_search('net.ac', $publicSuffixList) !== false);
	}

	public function testGetList()
	{
		copy(PDP_TEST_ROOT . '/_files/public_suffix_list.php', $this->cacheDir . '/public_suffix_list.php');
		$this->assertFileExists($this->cacheDir . '/public_suffix_list.php');
		$publicSuffixList = $this->listManager->getList();
        $this->assertInternalType('array', $publicSuffixList);
        $this->assertGreaterThanOrEqual(6000, count($publicSuffixList));
        $this->assertTrue(array_search('stuff-4-sale.org', $publicSuffixList) !== false);
        $this->assertTrue(array_search('net.ac', $publicSuffixList) !== false);
	}

}

