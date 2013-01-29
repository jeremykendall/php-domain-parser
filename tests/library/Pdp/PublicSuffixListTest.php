<?php

namespace Pdp;

class PublicSuffixListTest extends \PHPUnit_Framework_TestCase
{
	public function testLoadFromArray()
	{
        $file = realpath(dirname(__DIR__) . '/../../data/public-suffix-list.php');
		$psl = new PublicSuffixList(include $file);
		$this->listAssertions($psl);
	}

	public function testLoadFromFile()
	{
        $file = realpath(dirname(__DIR__) . '/../../data/public-suffix-list.php');
		$psl = new PublicSuffixList($file);
		$this->listAssertions($psl);
	}

	public function listAssertions(PublicSuffixList $psl)
	{
		$this->assertArrayHasKey('com', $psl);
		$this->assertArrayHasKey('*', $psl['uk']);
		$this->assertGreaterThanOrEqual(300, count($psl));
	}

}
