<?php

use DbTools\RestResource;

class TestRestResource extends RestResource
{
	public function getAllowedSort()
	{
		return ['id' => 't.id', 'created_at' => 't.created_at'];
	}

	public function getAllowedFilters()
	{
		return [];
	}

	public function getMaxPerPage()
	{
		return 200;
	}
	public function formatEntity($entity)
	{
		return $entity;
	}
}

class RestResourceTest extends PHPUnit_Framework_TestCase
{

///////////////////////////////////////////////////////////////////////////////
// Sort

	public function validSortOptions()
	{
		return [
			['', ''],
			[null, ''],
			[false, ''],
			[array('id'), 't.id ASC'],
			['id', 't.id ASC'],
			['-id', 't.id DESC'],
			['id,created_at', 't.id ASC,t.created_at ASC'],
			['id,-created_at', 't.id ASC,t.created_at DESC']
		];
	}

	public function invalidSortOptions()
	{
		return [
			// invalid syntax
			[' id'],
			['id '],
			['/id '],
			['id ,created_at'],
			['id, created_at'],
			['id,created_at '],
			['id,'],
			[',id'],

			// invalid fields (not exposed)
			['b'],
		];
	}

	/**
	 * @dataProvider validSortOptions
	 */
	public function testValidSort($sort, $sql)
	{
		$test = new TestRestResource();
		$test->processRequest([
			'sort' => $sort
		]);
		if ( $sql ) {
			$opt = $test->getModelFilters();
			$this->assertArrayHasKey('order_by', $opt);
			$this->assertEquals($sql, $opt['order_by']);
		}
	}

	/**
	 * @dataProvider invalidSortOptions
	 * @expectedException \InvalidArgumentException
	 */
	public function testInvalidSort($sort)
	{
		$test = new TestRestResource();
		$test->processRequest([
			'sort' => $sort
		]);
	}


///////////////////////////////////////////////////////////////////////////////
// Pager

	public function testPager()
	{

	}

}