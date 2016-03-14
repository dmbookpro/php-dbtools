<?php

class ConcreteApiTableModel extends ApiTableModel
{
	static protected $table_name = 'items';

	static public function getFieldsForApi()
	{
		return [
			'id' => true,
			'a' => true
		];
	}
}

class ApiTableModelTest extends PHPUnit_Framework_TestCase
{
	static public function setUpBeforeClass()
	{
		Database::setConfig([
			'default' => [
				'dsn' => 'sqlite::memory:'
			]
		]);
		$dbh = Database::get();
		$dbh->exec('CREATE TABLE items (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			a INT,
			b TEXT
		)');
		$dbh->exec(sprintf(
			'INSERT INTO items (a,b) VALUES(42, %s)',
			$dbh->quote('The Answer')
		));
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
			['id,a'],
		];
	}

	public function invalidFields()
	{
		return [
			['id '],
			[' id'],
			['id,'],
			['id, '],
			['id, a'],
			['id,a '],
			['fdsfds'],
			['b']
		];
	}

	/**
	 * @dataProvider validFields
	 */
	public function testValidFields($fields)
	{
		ConcreteApiTableModel::getListForApi([
			'fields' => $fields
		]);
	}

	/**
	 * @dataProvider invalidFields
	 * @expectedException InvalidArgumentException
	 */
	public function testInvalidFields($fields)
	{
		ConcreteApiTableModel::getListForApi([
			'fields' => $fields
		]);
	}

	public function testFields()
	{

	}

//////////////////////////////////////////////////////////////////////////////
// Sort

	public function validSortOptions()
	{
		return [
			['', ''],
			[null, ''],
			[false, ''],
			[array('id'), 't.id ASC'],
			['id', 't.id ASC'],
			['+id', 't.id ASC'],
			['-id', 't.id DESC'],
			['id,a', 't.id ASC,t.a ASC'],
			['id,+a', 't.id ASC,t.a ASC'],
			['id,-a', 't.id ASC,t.a DESC']
		];
	}

	public function invalidSortOptions()
	{
		return [
			// invalid syntax
			[' id'],
			['id '],
			['/id '],
			['id ,a'],
			['id, a'],
			['id,a '],
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
		$method = new ReflectionMethod(
			'ConcreteApiTableModel', 'convertSortToOrderBy'
		);
		$method->setAccessible(true);
		$this->assertEquals($sql, $method->invokeArgs(null, [$sort]));

		// test that this works
		ConcreteApiTableModel::getListForApi([
			'sort' => $sort
		]);
	}

	/**
	 * @dataProvider invalidSortOptions
	 * @expectedException InvalidArgumentException
	 */
	public function testInvalidSort($sort)
	{
		ConcreteApiTableModel::getListForApi([
			'sort' => $sort
		]);
	}

	public function testSort()
	{

	}

///////////////////////////////////////////////////////////////////////////////
// Pager

	public function testPager()
	{

	}

///////////////////////////////////////////////////////////////////////////////
// Embed

///////////////////////////////////////////////////////////////////////////////
// Combination of everything


}