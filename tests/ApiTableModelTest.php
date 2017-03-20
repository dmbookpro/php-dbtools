<?php

use DbTools\ApiTableModel;
use DbTools\Database;

class ConcreteApiTableModel extends ApiTableModel
{
	static protected $table_name = 'items';

	static protected function getQueryOptions()
	{
		return [
			'id' => null,
			
			'embed_subitems' => false
		];
	}

	static public function getFieldsForApi()
	{
		return [
			'id' => null,
			'a' => null
		];
	}

	static protected function afterGetList($dbh, array $opt, & $list)
	{
		if ( $opt['embed_subitems'] ) {
			foreach ($list as &$item) {
				$item['subitems'] = ['foobar'];
			}
		}
	}

	static protected function afterGetBy($dbh, array $opt, & $obj)
	{
		$obj = new static($obj);
		if ( $opt['embed_subitems'] ) {
			$obj['subitems'] = ['foobar'];
		}
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

	/**
	 * @expectedException \DbTools\ApiTableModelException
	 */
	public function testApiTableModelException()
	{
		ConcreteApiTableModel::getListForApi([
			'foo' => 'bar'
		]);
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
	 * @expectedException \DbTools\ApiTableModelException
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
	 * @expectedException \DbTools\ApiTableModelException
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

	public function validEmbedOptions()
	{
		return array(
			['subitems']
		);
	}

	public function invalidEmbedOptions()
	{
		return array(
			['something']
		);
	}

	/**
	 * @dataProvider validEmbedOptions
	 */
	public function testValidEmbedGetList($embed)
	{
		ConcreteApiTableModel::getListForApi([
			'embed' => $embed
		]);
	}

	/**
	 * @dataProvider validEmbedOptions
	 */
	public function testValidEmbedGetBy($embed)
	{
		$obj = ConcreteApiTableModel::getById(1);
		$obj->getValuesForApi([
			'embed' => $embed
		]);
	}

	/**
	 * @dataProvider invalidEmbedOptions
	 * @expectedException \DbTools\ApiTableModelException
	 */
	public function testInvalidEmbedGetList($embed)
	{
		ConcreteApiTableModel::getListForApi([
			'embed' => $embed
		]);
	}

	// *
	//  * @dataProvider invalidEmbedOptions
	//  * @expectedException InvalidArgumentException
	// * 
	// public function testInvalidEmbedGetBy($embed)
	// {
	// 	$obj = ConcreteApiTableModel::getById(1);
	// 	$obj->getValuesForApi([
	// 		'embed' => $embed
	// 	]);
	// }

	public function testEmbed()
	{
		$model = new ConcreteApiTableModel();

		$list = ConcreteApiTableModel::getListForApi([
			'pagination_meta' => false,
			'embed' => 'subitems'
		]);

		$this->assertNotEmpty($list);
		// $this->assertTrue(isset($list[0]['subitems']));

		// $obj = ConcreteApiTableModel::getById(1);
		// $this->assertNotNull($obj);

		// $values = $obj->getValuesForApi([
		// 	'embed' => 'subitems'
		// ]);
		// var_dump($values);
		// $this->assertNotEmpty($values->subitems);
	}

///////////////////////////////////////////////////////////////////////////////
// Combination of everything


}