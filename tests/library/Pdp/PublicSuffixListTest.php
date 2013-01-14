<?php

namespace Pdp;

class PublicSuffixListTest extends \PHPUnit_Framework_TestCase
{
	public function testLoadFromArray()
	{
		$array = include PDP_TEST_ROOT . '/_files/' . PublicSuffixListManager::PDP_PSL_PHP_FILE;
		$list = new PublicSuffixList($array);
		$this->listAssertions($list);
	}

	public function testLoadFromFile()
	{
		$list = new PublicSuffixList(PDP_TEST_ROOT . '/_files/' . PublicSuffixListManager::PDP_PSL_PHP_FILE);
		$this->listAssertions($list);
	}

	public function listAssertions(PublicSuffixList $list)
	{
		$this->assertArrayHasKey('com', $list);
		$this->assertArrayHasKey('*', $list['uk']);
		$this->assertGreaterThanOrEqual(300, count($list));
	}

}
