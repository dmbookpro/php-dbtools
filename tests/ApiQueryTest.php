<?php

use DbTools\ApiQuery;

class TestApiQuery extends ApiQuery
{
	public function getPublicFields()
	{
		return ['id' => null, 'created_at' => 'datetime'];
	}
	public function getSortableFields()
	{
		return ['id' => 't.id', 'created_at' => 't.created_at'];
	}
}

class MockApiModel
{
	static public function getList()
	{
		return [
			1 => ['id' => 1, 'name' => 'Sean', 'created_at' => '2017-01-01 00:42:00'],
			2 => ['id' => 2, 'name' => 'Jean', 'created_at' => '2017-01-02 04:20:00']
		];
	}

	static public function getById()
	{
		return ['id' => 3, 'name' => 'Matti', 'created_at' => '2017-01-03 03:00:00'];
	}
}

class ApiQueryTest extends PHPUnit_Framework_TestCase
{

	public function testFormatValuesRecursive()
	{
		$query = new ApiQuery();
		$values = [
			'object' => [
				'id' => 1,
				'name' => 'John'
			]
		];
		$formatted = $query->formatValues($values, [
			'object' => [
				'id' => null
			]
		]);
		$this->assertEquals(['object' => ['id' => 1]], $formatted);

		$values = [
			'objects' => [[
				'id' => 1,
				'name' => 'John'
			],[
				'id' => 2,
				'name' => 'Jane'
			]]
		];
		$formatted = $query->formatValues($values, [
			'objects' => [[
				'id' => null
			]]
		]);
		$this->assertEquals(['objects' => [['id' => 1],['id' => 2]]], $formatted);
	}

///////////////////////////////////////////////////////////////////////////////
// Fields

	public function validFields()
	{
		return [
			[''],
			[null],
			[false],
			[array()],
			[array('id')],
			['id'],
			['id,created_at'],
		];
	}

	public function invalidFields()
	{
		return [
			['id '],
			[' id'],
			['id,'],
			['id, '],
			['id, created_at'],
			['id,created_at '],
			['fdsfds'],
			['b']
		];
	}

	/**
	 * @dataProvider validFields
	 */
	public function testValidFields($fields)
	{
		$query = new TestApiQuery([
			'fields' => $fields
		]);
	}

	/**
	 * @dataProvider invalidFields
	 * @expectedException \InvalidArgumentException
	 */
	public function testInvalidFields($fields)
	{
		$query = new TestApiQuery([
			'fields' => $fields
		]);
	}

	public function testFields()
	{
		$query = new TestApiQuery([
			'pagination_meta' => false
		]);
		$list = MockApiModel::getList();
		$this->assertEquals(['data' => [
			['id' => 1, 'created_at' => '2017-01-01T00:42:00Z'],
			['id' => 2, 'created_at' => '2017-01-02T04:20:00Z']
		]], $query->formatList($list));

		$values = MockApiModel::getById();
		$this->assertEquals([
			'id' => 3,
			'created_at' => '2017-01-03T03:00:00Z'
		], $query->formatValues($values));

	}


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
		$query = new TestApiQuery([
			'sort' => $sort
		]);
		if ( $sql ) {
			$opt = $query->getListQueryOptions();
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
		$query = new TestApiQuery([
			'sort' => $sort
		]);
	}


///////////////////////////////////////////////////////////////////////////////
// Pager

	public function testPager()
	{

	}

}