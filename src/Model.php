<?php

/**
 * Licensed under the MIT license.
 *
 * For the full copyright and license information, please view the LICENSE file.
 *
 * @author Rémi Lanvin <remi@dmbook.pro>
 * @link https://github.com/dmbookpro/php-dbtools
 */

namespace DbTools;

/**
 * This is class to help implement models (the M in MVC). Technically a Model
 * can be viewed as a "versioned container".
 *
 * Any variable *not declared* in the child class will be collected here, in the
 * $values array. If the variable is modified, the original version will be
 * kept.
 *
 * Some definitions:
 *
 * - "value" is a value currently stored in the object, identified by a key (a name)
 *   All values are stored in $values.
 *
 * - "modified value" designates a value that has been changed since the object
 *   was created.
 *
 * - "original value" designates the value as it was when the object was created.
 *   If nothing has been changed, this is the same as the value. Otherwise, this
 *   is stored in $original_values.
 *
 * Possible actions are:
 *
 * - get all the current values (current state of the object) 
 *    => get($key), getValues()
 * - get all the original values (original state of the object)
 *    => getOriginal($key), getOriginalValues()
 * - get keys that have been modified
 *    |--> with current value
 *    |    => isModified($key), getDiff('modified')
 *    |--> with original value
 *    |    => isModified($key), getDiff('original')
 *    |--> with both
 *         => getDiff('both'), getDiff('both_merged')
 */
class Model implements \ArrayAccess, \IteratorAggregate
{
	/**
	 * @var array The current values
	 */
	private $values = array();

	/**
	 * @var array The original values
	 * When a value is modified, the original version will be stored here.
	 */
	private $original_values = array();

	static protected function toArray($var)
	{
		if ( is_array($var) ) {
			return $var;
		}
		elseif ( $var instanceof \stdClass ) {
			return (array) $var;
		}
		elseif ( $var instanceof self ) {
			return $var->getValues();
		}
		else {
			throw new \InvalidArgumentException('Invalid argument provided, cannot cast to an array');
		}
	}

	/**
	 * Constructor
	 */
	public function __construct($values = array())
	{
		$this->values = static::toArray($values);
		$this->original_values = $this->values;
	}

	/**
	 * Returns the current version of a key.
	 */
	public function & get($key)
	{
		$value = null;
		if ( array_key_exists($key, $this->values) ) {
			$value = & $this->values[$key];
		}
		return $value;
	}

	/**
	 * @return $this
	 */
	public function set($key, $value)
	{
		$this->values[$key] = $value;
		return $this;
	}

	/**
	 * Return all the current values as an array
	 *
	 * @return array
	 */
	public function getValues()
	{
		return $this->values;
	}

	/**
	 * Merge the model values with an array of new values.
	 *
	 * @param array $values
	 * @return $this
	 */
	public function merge($values)
	{
		if ( ! $values instanceof \Traversable ) {
			$values = static::toArray($values);
		}

		foreach ( $values as $key => $value ) {
			$this->set($key, $value);
		}

		return $this;
	}

	/**
	 * Return the original (unaltered) version of a value.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function getOriginal($key)
	{
		if ( array_key_exists($key, $this->original_values) ) {
			return $this->original_values[$key];
		}
		return null;
	}

	/**
	 * Set the original value, i.e. by-passing the changelog mechanism.
	 * Use this if you want to set an original value after the object has been created.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return $this
	 */
	public function setOriginal($key, $value)
	{
		$this->values[$key] = $value;
		$this->original_values[$key] = $value;
		return $this;
	}

	/**
	 * Return an array with the original state of the object
	 *
	 * @return array
	 */
	public function getOriginalValues()
	{
		return $this->original_values;
	}

	/**
	 * Return true if a value has been modified.
	 *
	 * @param string|array $keys The name of the value, or a array of values
	 *                           If this is an array, it'll return true if ANY
	 *                           of the value has been modified (this is like chaining OR)
	 * @return bool
	 */
	public function isModified($keys = null)
	{
		if ( $keys === null ) {
			return $this->original_values != $this->values;
		}

		if ( is_array($keys) ) {
			foreach ( $keys as $key ) {
				if ( $this->isModified($key) ) {
					return true;
				}
			}
			return false;
		}

		return array_key_exists($keys, $this->original_values) && $this->original_values[$keys] != $this->values[$keys];
	}

	/**
	 * Returns only modified values.
	 *
	 * @param string $type one of:
	 *             - 'original' return the keys that have been modified, along with their original value
	 *             - 'modified' return the keys that have been modified, along with their current (new) value
	 *             - 'both' return an array with 2 arrays, one for 'original' and one for 'modified'
	 *             - 'both_merged' return an array with the keys that have been modified and for each an array of [original, modified] values 
	 * @return array
	 */
	public function getDiff($type = 'both_merged')
	{
		$diff = array();

		$values = $this->values;
		switch ( $type ) {
			case 'original':
				foreach ( $this->original_values as $key => $value ) {
					if ( $value != $this->values[$key] ) {
						$diff[$key] = $this->original_values[$key];
					}
					unset($values[$key]);
				}
				foreach ( $values as $key => $value ) {
					$diff[$key] = null;
				}
				break;

			case 'modified':
				foreach ( $this->original_values as $key => $value ) {
					if ( $value != $this->values[$key] ) {
						$diff[$key] = $this->values[$key];
					}
					unset($values[$key]);
				}
				$diff += $values;
				break;

			case 'both':
				$diff = array(array(), array());
				foreach ( $this->original_values as $key => $value ) {
					if ( $value != $this->values[$key] ) {
						$diff[0][$key] = $this->original_values[$key];
						$diff[1][$key] = $this->values[$key];
					}
					unset($values[$key]);
				}
				foreach ( $values as $key => $value ) {
					$diff[0][$key] = null;
					$diff[1][$key] = $value;
				}
				break;

			case 'both_merged':
				foreach ( $this->original_values as $key => $value ) {
					if ( $value != $this->values[$key] ) {
						$diff[$key] = [$this->original_values[$key], $this->values[$key]];
					}
					unset($values[$key]);
				}
				foreach ( $values as $key => $value ) {
					$diff[$key] = [null, $value];
				}
				break;

			default:
				throw new InvalidArgumentException('getDiff() values must be one of the following: original, modified, both or both_merged');
		}

		return $diff;
	}

///////////////////////////////////////////////////////////////////////////////
// Object interface

	public function & __get($key)
	{
		return $this->get($key);
	}

	public function __set($key, $value)
	{
		return $this->set($key, $value);
	}

///////////////////////////////////////////////////////////////////////////////
// Array interface

	/**
	 * @param string $key
	 * @return boolean
	 */
	public function offsetExists($key)
	{
		return array_key_exists($key, $this->values);
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function & offsetGet($key)
	{
		return $this->get($key);
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public function offsetSet($key, $value)
	{
		$this->set($key, $value);
	}

	/**
	 * @param string $key
	 * @return void
	 */
	public function offsetUnset($key)
	{
		$this->set($key, null);
	}

///////////////////////////////////////////////////////////////////////////////
// Iterator interface

	public function getIterator()
	{
		return new \ArrayIterator($this->values);
	}
}