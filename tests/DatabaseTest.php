<?php

use DbTools\Database;

class DatabaseTest extends PHPUnit_Framework_TestCase
{
	public function testSetConfig()
	{
		$dsn = 'sqlite::memory:';

		Database::setConfig([
			'default' => [
				'dsn' => $dsn,
				'attributes' => [
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
				]
			]
		]);

		$c = Database::getConfig();
		$this->assertEquals('PDO', $c['default']['class'], 'setConfig preserves default values');
		$this->assertEquals($dsn, $c['default']['dsn'], 'setConfig merges values');
		$this->assertEquals(PDO::ERRMODE_EXCEPTION, $c['default']['attributes'][PDO::ATTR_ERRMODE], 'setConfig preserves default attributes');
		$this->assertEquals(PDO::FETCH_ASSOC, $c['default']['attributes'][PDO::ATTR_DEFAULT_FETCH_MODE], 'setConfig preserves default attributes');
	}
}