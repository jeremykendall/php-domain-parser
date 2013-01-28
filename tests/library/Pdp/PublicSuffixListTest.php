<?php

namespace Pdp;

class PublicSuffixListTest extends \PHPUnit_Framework_TestCase
{
	public function testLoadFromArray()
	{
		$array = include PDP_TEST_ROOT . '/_files/' . PublicSuffixListManager::PDP_PSL_PHP_FILE;
		$psl = new PublicSuffixList($array);
		$this->listAssertions($psl);
	}

	public function testLoadFromFile()
	{
		$psl = new PublicSuffixList(PDP_TEST_ROOT . '/_files/' . PublicSuffixListManager::PDP_PSL_PHP_FILE);
		$this->listAssertions($psl);
	}

	public function listAssertions(PublicSuffixList $psl)
	{
		$this->assertArrayHasKey('com', $psl);
		$this->assertArrayHasKey('*', $psl['uk']);
		$this->assertGreaterThanOrEqual(300, count($psl));
	}

}
