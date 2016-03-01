<?php

class TableModel
{
	use OrmTrait;

	static protected $table_name = 'items';

	static protected function getQueryOptions()
	{
		return [
			'replace_list_completely' => false,
			'inject_list' => false,
			'inject_getby' => false,
		];
	}

	static protected function computeQueryParts($dbh, $opt, & $where, & $join, & $select)
	{
		static::computeStandardWhereClause($dbh, ['id'], $opt, $where);
	}

	static protected function afterGetList($dbh, $opt, & $list)
	{
		if ( $opt['replace_list_completely'] ) {
			$list = ['Yep, it worked'];
		}

		if ( $opt['inject_list'] ) {
			$list['injected'] = ['Yep, it worked'];
		}
	}

	static protected function afterGetBy($dbh, $opt, & $obj)
	{
		if ( $opt['inject_getby'] ) {
			$obj->it_worked = true;
		}
	}
}

class OrmTraitTest extends PHPUnit_Framework_TestCase
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

	public function standardWhereClauses()
	{
		return [
			[['id' => 1], ["t.id = '1'"]],
			[['id' => 0], ["t.id = '0'"]],
			[['id' => 'foobar'], ["t.id = 'foobar'"]],
			[['id' => [1,2,3]], ["t.id IN ('1','2','3')"]],
			[['id' => [3]], ["t.id = '3'"]],
			[['id' => [0]], ["t.id = '0'"]],
			[['id' => 'NULL'], ["t.id IS NULL"]],
			[['id' => 'NOT NULL'], ["t.id IS NOT NULL"]],
			[['id' => [1,2,'NULL']], ["(t.id IN ('1','2') OR t.id IS NULL)"]],
			[['id' => [1,2,null]], ["t.id IN ('1','2')"]],
			[['id' => [1,2,'']], ["t.id IN ('1','2','')"]],
			[['id' => null], []],
			[['id' => false], []],
			[['id' => ''], ["t.id = ''"]],
			[['id' => []], ["t.id = ''"]],
			[['id' => [null]], ["t.id = ''"]],
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
			'TableModel', 'computeStandardWhereClause'
		);
		$method->setAccessible(TRUE);
		$method->invokeArgs(null, [$dbh, $fields, $opt, &$where]);
		$this->assertEquals($expected, $where);
	}

	public function testAfterGetListHooks()
	{
		$list = TableModel::getList(['inject_list' => true]);
		$this->assertTrue(isset($list['injected']), 'afterGetList() worked and allowed me inject something after the fetch');
		$this->assertEquals(['Yep, it worked'], $list['injected']);

		$list = TableModel::getList(['replace_list_completely' => true]);
		$this->assertEquals(['Yep, it worked'], $list, 'afterGetList() worked and allowed me to completely replace the list');
	}

	public function testAfterGetByHooks()
	{
		$list = TableModel::getList();
		$this->assertNotEmpty($list, 'Crap, no items to test with!');

		$obj = TableModel::getById(key($list), ['inject_getby' => true]);
		$this->assertEquals(true, $obj->it_worked, 'afterGetBy() worked and allowed me to modify the object');
	}
}