<?php

namespace Pdp;

class PublicSuffixListTest extends \PHPUnit_Framework_TestCase
{
	protected $list;

	protected function setUp()
	{
		$manager = new PublicSuffixListManager(PDP_TEST_ROOT . '/_files');
		$this->list = new PublicSuffixList($manager->getList());
	}

	protected function tearDown()
	{
		$this->list = null;
	}

	public function testObject()
	{
		$this->assertInstanceOf('\ArrayObject', $this->list);
		$this->assertGreaterThanOrEqual(6000, $this->list->count());
	}

	/**
	 * @dataProvider searchDataProvider
	 */
	public function testSearch($label, $result)
	{
		$this->assertTrue($this->list->search($label) !== $result);
	}
	
	public function searchDataProvider()
	{
		return array(
			array('com', true),
			array('co.uk', true),
			array('com.au', true),
		//	array('wtf.is.this', false),
			array('!congresodelalengua3.ar', true),
			array('*.bd', true),
			array('kamikoani.akita.jp', true),
		//	array('tired.of.searching', false)
		);
	}
}
