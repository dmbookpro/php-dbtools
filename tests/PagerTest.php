<?php

use DbTools\Pager;
use DbTools\Database;

class PagerTest extends PHPUnit_Framework_TestCase
{

	public function invalidPageValues()
	{
		return [
			[array()],
			['foobar'],
			[new stdClass()],
			[0],
		];
	}
	/**
	 * @dataProvider invalidPageValues
	 * @expectedException InvalidArgumentException
	 */
	public function testSetCurrentPageException($n)
	{
		$pager = new Pager();
		$pager->setCurrentPage($n);
	}

	public function invalidPerPageValues()
	{
		return [
			[array()],
			['foobar'],
			[new stdClass()],
			[0],
			[-1],
		];
	}

	/**
	 * @dataProvider invalidPerPageValues
	 * @expectedException InvalidArgumentException
	 */
	public function testSetPerPageException($n)
	{
		$pager = new Pager();
		$pager->setPerPage($n);
	}

	public function testSetTotal()
	{
		$pager = new Pager(20, 2);
		$this->assertEquals(1, $pager->getCurrentPage(), 'current page is capped by the total');
		$this->assertEquals(0, $pager->getTotal());
		$this->assertEquals(1, $pager->getNbPages(), 'There is always one page');

		$pager->setTotal(20);
		$this->assertEquals(1, $pager->getCurrentPage());
		$this->assertEquals(20, $pager->getTotal());
		$this->assertEquals(1, $pager->getNbPages(), 'There is one page');

		$pager->setTotal(21);
		$this->assertEquals(2, $pager->getCurrentPage());
		$this->assertEquals(2, $pager->getNbPages(), 'There are two pages');

		$pager->setTotal(0);
		$this->assertEquals(1, $pager->getCurrentPage(), 'current page is capped by the total');
		$this->assertEquals(0, $pager->getTotal());
		$this->assertEquals(1, $pager->getNbPages(), 'There is always one page');
	}

	public function testNegativeCurrentPage()
	{
		$pager = new Pager(20,-1);
		$this->assertEquals(1, $pager->getCurrentPage());

		$pager->setTotal(20);
		$this->assertEquals(1, $pager->getCurrentPage());

		$pager->setTotal(21);
		$this->assertEquals(2, $pager->getCurrentPage());
	}

	public function testQueryForTotal()
	{
		Database::setConfig(['default' => ['dsn' => 'sqlite::memory:']]);
		$dbh = Database::get();

		$pager = new Pager();

		$pager->queryForTotal(
			$dbh,
			'SELECT 42'
		);
		$this->assertEquals(42, $pager->getTotal());

		$stmt = $dbh->prepare('SELECT 666');
		$pager->queryForTotal($stmt);
		$this->assertEquals(666, $pager->getTotal());
	}

	public function testGetLimitClause()
	{
		$pager = new Pager(10,1);
		$pager->setTotal(42);

		$this->assertEquals('0,10', $pager->getLimitClause());
		$pager->setCurrentPage(2);
		$this->assertEquals('10,10', $pager->getLimitClause());
		$pager->setPerPage(15);
		$this->assertEquals('15,15', $pager->getLimitClause());
	}

	public function testHaveToPaginate()
	{
		$pager = new Pager(10,1);
		$this->assertFalse($pager->haveToPaginate());

		$pager->setTotal(11);
		$this->assertTrue($pager->haveToPaginate());
	}

	public function testPreviousNextPages()
	{
		$pager = new Pager(10,5);
		$pager->setTotal(100);
		$this->assertEquals(10, $pager->getNbPages());

		$this->assertEquals(6, $pager->getNextPage());
		$this->assertEquals(4, $pager->getPreviousPage());

		$pager->setCurrentPage(1);
		$this->assertEquals(2,$pager->getNextPage());
		$this->assertEquals(1,$pager->getPreviousPage());

		$pager->setCurrentPage(10);
		$this->assertEquals(10,$pager->getNextPage());
		$this->assertEquals(9,$pager->getPreviousPage());

		$pager->setCurrentPage(21);
		$this->assertEquals(10,$pager->getCurrentPage());
		$this->assertEquals(10,$pager->getNextPage());
		$this->assertEquals(9,$pager->getPreviousPage());
	}

	public function testGetPagesBeforeAndAfter()
	{
		$pager = new Pager(10,1);

		$this->assertEquals([1],$pager->getPagesBeforeAndAfter());
		$pager->setTotal(30);
		$this->assertEquals([1,2,3],$pager->getPagesBeforeAndAfter());

		$pager->setTotal(100);
		$this->assertEquals([1,2,3,4],$pager->getPagesBeforeAndAfter());

		$pager->setCurrentPage(2);
		$this->assertEquals([1,2,3,4,5],$pager->getPagesBeforeAndAfter());

		$pager->setCurrentPage(9);
		$this->assertEquals([6,7,8,9,10],$pager->getPagesBeforeAndAfter());
		
		$pager->setCurrentPage(10);
		$this->assertEquals([7,8,9,10],$pager->getPagesBeforeAndAfter());
	}
}