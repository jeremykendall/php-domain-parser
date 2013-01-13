<?php

namespace Pdp;

class PublicSuffixListTest extends \PHPUnit_Framework_TestCase
{
	protected $list;

	protected function setUp()
	{
        parent::setUp();
        $publicSuffixListArray = include PDP_TEST_ROOT . '/_files/public_suffix_list.php';
		$this->list = new PublicSuffixList($publicSuffixListArray);
	}

	protected function tearDown()
	{
		$this->list = null;
        parent::tearDown();
	}

	public function testObject()
	{
		$this->assertInstanceOf('\Pdp\PublicSuffixList', $this->list);
		$this->assertGreaterThanOrEqual(300, $this->list->count());
	}

	/**
	 * @dataProvider searchDataProvider
     * @todo Review this test. Needs to be refactored.
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
