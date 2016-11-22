<?php

use DbTools\Model;

class ModelTest extends PHPUnit_Framework_TestCase
{
	public function testConstructorAndGet()
	{
		$values = [
			'username' => 'john',
			'age' => '42'
		];
		$m = new Model($values);

		$this->assertEquals($values, $m->getValues(), 'getValues() returns the values');
	}

	public function testConstrutorWithOtherTypes()
	{
		$values = (object) ['id' => 1];
		$m = new Model($values);
		$this->assertEquals(['id' => 1], $m->getValues(), 'Constructor with stdClass works');

		$m = new Model($m);
		$this->assertEquals(['id' => 1], $m->getValues(), 'Constructor with another Model works');
	}

	/**
	 * @depends testConstructorAndGet
	 */
	public function testObjectInterface()
	{
		$m = new Model([
			'username' => 'john',
			'address' => [
				'street' => ''
			]
		]);

		$this->assertEquals('john', $m->username, '__get() returns the value');
		$m->username = 'jane';
		$this->assertEquals('jane', $m->username, '__set() sets the value');

		// test that this doesnt fire "indirect modification of overloaded property"
		$m->address['street'] = '42 foobar street';
		$this->assertEquals('42 foobar street', $m->address['street']);

		// test that this doesn't fire " Only variable references should be returned by reference"
		$this->assertNull($m->foobar);
	}

	/**
	 * @depends testConstructorAndGet
	 */
	public function testArrayAccess()
	{
		$m = new Model([
			'username' => 'john',
			'address' => [
				'street' => ''
			]
		]);
		$this->assertEquals('john', $m['username'], 'offsetGet returns the value');
		$m['username'] = 'jane';
		$this->assertEquals('jane', $m['username'], 'offsetSet sets the value');
		$this->assertTrue(isset($m['username']), 'offsetExists works');

		// test that this doesnt fire "indirect modification of overloaded element"
		$m['address']['street'] = '42 foobar street';
		$this->assertEquals('42 foobar street', $m['address']['street']);

		// test that this doesn't fire " Only variable references should be returned by reference"
		$this->assertNull($m['foobar']);

	}

	public function testIterator()
	{
		$values = [
			'username' => 'john',
			'age' => '42'
		];
		$m = new Model($values);
		$collected_values = [];
		foreach ( $m as $key => $value ) {
			$collected_values[$key] = $value;
		}
		$this->assertEquals($values, $collected_values, 'Values collected with foreach are the same');

		// try messing up during the loop
		$collected_values = [];
		foreach ( $m as $key => $value ) {
			$m->{$key.'2'} = $value;
			$collected_values[$key] = $value;
		}
		$this->assertEquals($values, $collected_values, 'Foreach is not disturbed if the object is modified during the loop');
	}

	/**
	 * @depends testConstructorAndGet
	 */
	public function testMerge()
	{
		$v1 = array(
			'name' => 'Rémi',
			'age' => 24,
			'id' => [1,2,3,4],
			'files' => [1 => new stdClass(), 2 => new stdClass()]
		);

		$v2 = array(
			'name' => 'Rémi',
			'age' => 30,
			'id' => [3,4,5],
			'files' => [2 => new stdClass()],
			'new_field' => 'Hello!'
		);

		$m = (new Model($v1))->merge($v2);
		$this->assertEquals($v2, $m->getValues(), 'merge() merges the values');

		$m = (new Model($v1))->merge((object) $v2);
		$this->assertEquals($v2, $m->getValues(), 'merge() works with stdClass');

		$m = (new Model($v1))->merge(new Model($v2));
		$this->assertEquals($v2, $m->getValues(), 'merge() works with another Model');
	}

	/**
	 * @depends testObjectInterface
	 * @depends testMerge
	 */
	public function testVersionning()
	{
		$v1 = array(
			'name' => 'Rémi',
			'age' => 24,
			'id' => [1,2,3,4],
			'files' => [1 => new stdClass(), 2 => new stdClass()],
			'old_field' => 'foobar',
			'identical' => 'This field doesnt change'
		);

		$v2 = array(
			'name' => 'Rémi',
			'age' => 30,
			'id' => [3,4,5],
			'files' => [2 => new stdClass()],
			'new_field' => 'Hello!',
			'old_field' => null,
			'identical' => 'This field doesnt change'
		);

		$m = new Model($v1);
		$m->name = 'Rémy';
		$this->assertTrue($m->isModified('name'), 'isModified() returns true when the field has been modified');
		$this->assertTrue($m->isModified(['name','age']), 'isModified() returns true when one of the field has been modified');
		$this->assertEquals('Rémi', $m->getOriginal('name'), 'getOriginal() returns the original value of the field');
		$this->assertEquals($v1, $m->getOriginalValues(), 'getOriginalValues() returns the original values');
		$this->assertEquals(['name' => 'Rémi'], $m->getDiff('original'), 'getDiff() returns the new value');
		$this->assertEquals(['name' => 'Rémy'], $m->getDiff('modified'), 'getDiff() returns the new value');


		$m = (new Model($v1))->merge($v2);
		$this->assertEquals(array(
			'age' => 24,
			'id' => [1,2,3,4],
			'files' => [1 => new stdClass(), 2 => new stdClass()],
			'new_field' => null,
			'old_field' => 'foobar'
		), $m->getDiff('original'));

		$this->assertEquals(array(
			'age' => 30,
			'id' => [3,4,5],
			'files' => [2 => new stdClass()],
			'new_field' => 'Hello!',
			'old_field' => null
		), $m->getDiff('modified'));

		$this->assertEquals(array(
			0 => [
				'age' => 24,
				'id' => [1,2,3,4],
				'files' => [1 => new stdClass(), 2 => new stdClass()],
				'new_field' => null,
				'old_field' => 'foobar'
			],
			1 => [
				'age' => 30,
				'id' => [3,4,5],
				'files' => [2 => new stdClass()],
				'new_field' => 'Hello!',
				'old_field' => null
			]
		), $m->getDiff('both'));

		$this->assertEquals(array(
			'age' => [$v1['age'], $v2['age']],
			'id' => [$v1['id'], $v2['id']],
			'files' => [$v1['files'], $v2['files']],
			'new_field' => [null, $v2['new_field']],
			'old_field' => [$v1['old_field'], $v2['old_field']]
		), $m->getDiff('both_merged'));
	}

	public function testGetEmptyDiff()
	{
		$v1 = new Model(array(
			'name' => 'Rémi',
			'age' => 24,
			'id' => [1,2,3,4],
			'files' => [1 => new stdClass(), 2 => new stdClass()],
			'old_field' => 'foobar',
			'identical' => 'This field doesnt change'
		));
		$this->assertEquals(array(array(),array()), $v1->getDiff('both'));
	}

	public function testSetOriginal()
	{
		$m = new Model([
			'value' => 'foobar'
		]);

		$this->assertFalse($m->isModified('value'));
		$m->setOriginal('value', 'Something else');
		$this->assertFalse($m->isModified('value'));
		$this->assertEquals('Something else', $m->value);
	}

	public function testMultipleModifications()
	{
		$model = new Model(['value' => '']);
		$this->assertFalse($model->isModified(), 'isModified is false at the beginning');

		$model->value = 'foobar';
		$this->assertTrue($model->isModified('value'), 'Value is flagged as modified');
		$this->assertTrue($model->isModified(), 'Whole object is modified');
		$this->assertEquals(['value' => ''], $model->getOriginalValues());
		$this->assertEquals(['value' => 'foobar'], $model->getDiff('modified'));

		$model->value = 'foobar2';
		$this->assertTrue($model->isModified());
		$this->assertTrue($model->isModified('value'));
		$this->assertEquals(['value' => ''], $model->getOriginalValues(), 'Original value is preserved with __set()');
		$this->assertEquals(['value' => 'foobar2'], $model->getDiff('modified'));

		$model = new Model(['value' => '']);
		$model->merge(['value' => 'foobar']);
		$this->assertTrue($model->isModified());
		$this->assertTrue($model->isModified('value'));
		$this->assertEquals(['value' => ''], $model->getOriginalValues());
		$this->assertEquals(['value' => 'foobar'], $model->getDiff('modified'));

		$model->merge(['value' => 'foobar2']);
		$this->assertTrue($model->isModified());
		$this->assertTrue($model->isModified('value'));
		$this->assertEquals(['value' => ''], $model->getOriginalValues(), 'Original value is preserved with merge()');
		$this->assertEquals(['value' => 'foobar2'], $model->getDiff('modified'));
	}

	public function testModificationAndReset()
	{
		$model = new Model(['value' => '']);

		$model->value = 'foobar';
		$this->assertTrue($model->isModified('value'));
		$this->assertTrue($model->isModified());
		$this->assertEquals(['value' => ''], $model->getOriginalValues());
		$this->assertEquals(['value' => 'foobar'], $model->getDiff('modified'));
		$this->assertEquals(['value' => ['','foobar']], $model->getDiff('both_merged'));

		$model->value = '';
		$this->assertFalse($model->isModified('value'), 'isModified() is false when value is reset to original');
		$this->assertFalse($model->isModified(), 'global isModified() if false when value is reset to original');
		$this->assertEquals(array('value' => ''),$model->getOriginalValues());
		$this->assertEquals(array(), $model->getDiff('modified'));
		$this->assertEquals(array(), $model->getDiff('both_merged'));

		$model = new Model(['value' => '']);
		$model->merge(['value' => 'foobar']);
		$model->merge(['value' => '']);
		$this->assertFalse($model->isModified('value'), 'isModified() is false when value is reset to original');
		$this->assertFalse($model->isModified(), 'global isModified() if false when value is reset to original');
		$this->assertEquals(array('value' => ''),$model->getOriginalValues());
		$this->assertEquals(array(), $model->getDiff('modified'));
		$this->assertEquals(array(), $model->getDiff('both_merged'));
	}

	public function testIsModifiedWithBool()
	{
		$model = new Model(['bool' => 0]);
		$model->merge(array('bool' => '0'));
		$this->assertFalse($model->isModified('bool'));
		$this->assertEquals(array('bool' => 0), $model->getOriginalValues());
		$this->assertEquals(array(), $model->getDiff('modified'));
		$this->assertEquals(array(), $model->getDiff('both_merged'));
		$this->assertEquals(array(array(), array()), $model->getDiff('both'));

		$model->merge(array('bool' => false));
		$this->assertFalse($model->isModified('bool'));
		$this->assertEquals(array('bool' => 0), $model->getOriginalValues());
		$this->assertEquals(array(), $model->getDiff('modified'));
		$this->assertEquals(array(), $model->getDiff('both_merged'));
		$this->assertEquals(array(array(), array()), $model->getDiff('both'));

		$model->bool = true;
		$this->assertTrue($model->isModified('bool'));
		$this->assertEquals(['bool' => 0], $model->getOriginalValues());
		$this->assertEquals(['bool' => true], $model->getDiff('modified'));
		$this->assertEquals(['bool' => [0,true]], $model->getDiff('both_merged'));
		$this->assertEquals(array(['bool' => 0],['bool' => true]), $model->getDiff('both'));
	}

	public function testIsModifiedWithArray()
	{
		$model = new Model([
			'empty' => []
		]);
		$model->merge([
			'empty' => []
		]);
		$this->assertFalse($model->isModified('empty'));

		$model->setOriginal('empty', []);
		$this->assertFalse($model->isModified('empty'));

		$model = new Model([
			'assoc' => ['1'=>new stdClass(), '6' => new stdClass()]
		]);
		$model->merge([
			'assoc' => ['1' => new stdClass()]
		]);
		$this->assertTrue($model->isModified('assoc'));

		$model = new Model([
			'assoc' => ['1'=>new stdClass(), '6' => new stdClass()]
		]);
		$model->merge([
			'assoc' => ['1' => new stdClass(), '6' => new stdClass(), '12' => new stdClass()]
		]);
		$this->assertTrue($model->isModified('assoc'));

		$model = new Model([
			'arr' => [1,2,3]
		]);
		$model->merge([
			'arr' => [2,1]
		]);
		$this->assertTrue($model->isModified('arr'));

		$model = new Model([
			'arr' => [new stdClass(), new stdClass()]
		]);
		$model->merge([
			'arr' => [new stdClass()]
		]);
		$this->assertTrue($model->isModified('arr'));
	}

	public function testIndirectModification()
	{
		$model = new Model([
			'age' => 0,
			'address' => [
				'street' => ''
			]
		]);

		$model->address['street'] = 'Foobar Street';
		$this->assertTrue($model->isModified('address'), 'Indirect modification is allowed, and should be tracked too');
	}

}