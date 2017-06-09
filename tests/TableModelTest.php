<?php

use DbTools\TableModel;
use DbTools\Database;

class ConcreteTableModel extends TableModel
{
	static protected $table_name = 'items';

	static protected function getQueryOptions()
	{
		return [
			'id' => null,
			'replace_list_completely' => false,
			'inject_list' => false,
			'inject_getby' => false,
		];
	}

	static protected function computeQueryParts($dbh, array &$opt, & $where, & $join, & $select)
	{
		static::computeStandardWhereClause($dbh, ['id'], $opt, $where);
	}

	static protected function afterGetList($dbh, array $opt, & $list)
	{
		if ( $opt['replace_list_completely'] ) {
			$list = ['Yep, it worked'];
		}

		if ( $opt['inject_list'] ) {
			$list['injected'] = ['Yep, it worked'];
		}
	}

	static protected function afterGetBy($dbh, array $opt, & $obj)
	{
		$obj = new static($obj);
		if ( $opt['inject_getby'] ) {
			$obj->it_worked = true;
		}
	}
}

class TableModelTest extends PHPUnit_Framework_TestCase
{
	static protected $id;

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
		$dbh->exec(sprintf(
			'INSERT INTO items (a,b) VALUES(42, %s)',
			$dbh->quote('The Answer of life, etc.')
		));
		self::$id = $dbh->lastInsertId();
	}

	public function testGetList()
	{
		$list = ConcreteTableModel::getList([
		]);
		$this->assertInternalType('array', $list);
		$this->assertCount(2, $list);
		$this->assertNotEmpty(ConcreteTableModel::getLastSelectQuery());

		$list = ConcreteTableModel::getList([
			'fetch_mode' => false
		]);
		$this->assertInstanceOf('PDOStatement', $list);
		$this->assertInternalType('array', $list->fetchAll());
	}

	public function testGroupBy()
	{
		$list = ConcreteTableModel::getList([
			'group_by' => 't.a'
		]);
		$this->assertInternalType('array', $list);
		$this->assertCount(1, $list);
	}

	public function testOrderBy()
	{
		$list = ConcreteTableModel::getList([
			'order_by' => 't.id DESC'
		]);
		$this->assertInternalType('array', $list);
		$this->assertCount(2, $list);
	}

	public function testLimit()
	{
		$list = ConcreteTableModel::getList([
			'limit' => 1
		]);
		$this->assertCount(1, $list);
	}

	public function testGetBy()
	{
		$obj = ConcreteTableModel::getById(self::$id);
		$this->assertInstanceOf('ConcreteTableModel', $obj);
		$this->assertNotEmpty(ConcreteTableModel::getLastSelectQuery());
	}

	public function testCount()
	{
		$count = ConcreteTableModel::getCount();
		$this->assertEquals(2, $count);
	}

///////////////////////////////////////////////////////////////////////////////
// Options

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testExtraneousOptionsGetList()
	{
		ConcreteTableModel::getList([
			'embed' => 'foobar'
		]);
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testExtraneousOptionsGetBy()
	{
		ConcreteTableModel::getById(1, [
			'embed' => 'foobar'
		]);
	}

///////////////////////////////////////////////////////////////////////////////
// Compute where clause

	public function standardWhereClauses()
	{
		return [
			// option            expected sql

			// equal, short version
			[['id' => 1],            ["t.id = '1'"]],
			[['id' => 0],            ["t.id = '0'"]],
			[['id' => 'foobar'],     ["t.id = 'foobar'"]],
			[['id' => null],         []],
			[['id' => false],        []],
			[['id' => ''],           ["t.id = ''"]],
			[['id' => []],           ["t.id = ''"]],
			[['id' => [null]],       ["t.id = ''"]],
			// equal, long version
			[['id' => ['eq' => 'foobar']], ["t.id = 'foobar'"]],

			// not equal
			[['id' => ['neq' => 'foobar']], ["t.id != 'foobar'"]],

			// less than
			[['id' => ['lt' => 'foobar']], ["t.id < 'foobar'"]],

			// less than or equal
			[['id' => ['lte' => 'foobar']], ["t.id <= 'foobar'"]],

			[['id' => ['gt' => 'foobar']], ["t.id > 'foobar'"]],

			[['id' => ['gte' => 'foobar']], ["t.id >= 'foobar'"]],
			
			// in - short version
			[['id' => [1,2,3]],      ["t.id IN ('1','2','3')"]],
			[['id' => [3]],          ["t.id = '3'"]],
			[['id' => [0]],          ["t.id = '0'"]],
			[['id' => [1,2,null]],   ["t.id IN ('1','2')"]],
			[['id' => [1,2,'']],     ["t.id IN ('1','2','')"]],
			// in - long version
			[['id' => ['in' => [1,2]]],          ["t.id IN ('1','2')"]],
			[['id' => ['in' => [0]]],          ["t.id IN ('0')"]],
			[['id' => ['in' => 0]],          ["t.id IN ('0')"]],
			[['id' => ['in' => 'abc']],          ["t.id IN ('abc')"]],

			// is null, is not null - short version (backward compat, should be removed)
			[['id' => 'NULL'],       ["t.id IS NULL"]],
			[['id' => 'NOT NULL'],   ["t.id IS NOT NULL"]],
			[['id' => [1,2,'NULL']], ["(t.id IN ('1','2') OR t.id IS NULL)"]],
			// is null, is not null - long version
			[['id' => ['is' => 'null']], ['t.id IS NULL']],
			[['id' => ['isnt' => 'null']], ['t.id IS NOT NULL']],

			// between
			[['id' => ['between' => [0,10]]],    ["t.id BETWEEN '0' AND '10'"]],
			// between, backward compatibility
			[['id' => ['between' => [null,10]]], ["t.id <= '10'"]],
			[['id' => ['between' => [10,null]]], ["t.id >= '10'"]],
			[['id' => ['between' => [0,null]]],  ["t.id >= '0'"]],
			[['id' => ['between' => [null,0]]],  ["t.id <= '0'"]]
		];
	}

	/**
	 * @dataProvider standardWhereClauses
	 */
	public function testComputeStandardWhereClause($opt, $expected)
	{
		$dbh = Database::get();

		$fields = ['id'];

		$where = [];

		$method = new ReflectionMethod(
			'DbTools\TableModel', 'computeStandardWhereClause'
		);
		$method->setAccessible(true);
		$method->invokeArgs(null, [$dbh, $fields, $opt, &$where]);
		$this->assertEquals($expected, $where);
	}

	public function invalidStandardWhereClauses()
	{
		return array(
			[['id' => ['between' => ['A','B','C']]]],
			[['id' => ['between' => ['A' => 1,'B' => 2]]]],
		);
	}

	/**
	 * @dataProvider invalidStandardWhereClauses
	 * @expectedException InvalidArgumentException
	 */
	public function testComputeStandardWhereClauseInvalid($opt)
	{
		$dbh = Database::get();

		$fields = ['id'];

		$where = [];

		$method = new ReflectionMethod(
			'DbTools\TableModel', 'computeStandardWhereClause'
		);
		$method->setAccessible(true);
		$method->invokeArgs(null, [$dbh, $fields, $opt, &$where]);
	}

	public function limitClauses()
	{
		return [
			['1', true],
			['1,1', true],
			['10,1', true],
			['10, 1', true],
			['10 , 1', true],
			['10,1,1', false],
			['foobar', false]
		];
	}

	/**
	 * @dataProvider limitClauses
	 */
	public function testValidateLimit($str, $expected_result)
	{
		$this->assertEquals($expected_result, TableModel::isValidLimit($str));
	}

///////////////////////////////////////////////////////////////////////////////
// Before and after hooks

	public function testAfterGetListHooks()
	{
		$list = ConcreteTableModel::getList(['inject_list' => true]);
		$this->assertTrue(isset($list['injected']), 'afterGetList() worked and allowed me inject something after the fetch');
		$this->assertEquals(['Yep, it worked'], $list['injected']);

		$list = ConcreteTableModel::getList(['replace_list_completely' => true]);
		$this->assertEquals(['Yep, it worked'], $list, 'afterGetList() worked and allowed me to completely replace the list');
	}

	public function testAfterGetByHooks()
	{
		$list = ConcreteTableModel::getList();
		$this->assertNotEmpty($list, 'Crap, no items to test with!');

		$obj = ConcreteTableModel::getById(key($list), ['inject_getby' => true]);
		$this->assertEquals(true, $obj->it_worked, 'afterGetBy() worked and allowed me to modify the object');
	}

///////////////////////////////////////////////////////////////////////////////
// Json Helpers

	public function testParseJson()
	{
		$this->assertEquals([],TableModel::parseJson('[]'));

		$this->assertEquals(['foobar' => true],TableModel::parseJson('[]', ['foobar' => true]));
		$this->assertEquals(['foobar' => false], TableModel::parseJson('{"foobar":false}', ['foobar' => true]));
		$this->assertEquals(['foobar' => true], TableModel::parseJson('', ['foobar' => true]));
		$this->assertEquals(['foobar' => true], TableModel::parseJson('{}', ['foobar' => true]));
		$this->assertEquals(['foobar' => true], TableModel::parseJson('null', ['foobar' => true]));

		$this->assertEquals([],TableModel::parseJson(array()));
		$this->assertEquals([],TableModel::parseJson(new stdClass()));
		$this->assertEquals([],TableModel::parseJson(null));
	}

	public function invalidJson()
	{
		return array(
			['foobar'],
			['"[]"']
		);
	}

	/**
	 * @dataProvider invalidJson
	 * @expectedException InvalidArgumentException
	 */
	public function testInvalidJson($json)
	{
		TableModel::parseJson($json);
	}

///////////////////////////////////////////////////////////////////////////////
// Date helpers

	public function validDates()
	{
		return [
			// input        datetime
			['2016-06-01', '2016-06-01 00:00:00'],
			['2016-06-01 12:42:10', '2016-06-01 12:42:10'],
			[new DateTime('2016-06-01'), '2016-06-01 00:00:00'],
		];
	}

	/**
	 * @dataProvider validDates
	 */
	public function testParseDate($input, $datetime)
	{
		$this->assertEquals($datetime, TableModel::parseDate($input)->format('Y-m-d H:i:s'));
	}

	public function invalidDates()
	{
		return [
			['foobar'],
			[null],
			[''],
			// [array()], // TypeError in PHP 7
			// [new stdClass()], // TypeError in PHP 7
		];
	}

	/**
	 * @dataProvider invalidDates
	 * @expectedException InvalidArgumentException
	 */
	public function testParseDateInvalid($input)
	{
		TableModel::parseDate($input);
	}
}
