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
 * This class regroups helper functions for Model classes to work with a Table
 * (typically MySQL, although in theory it should work for everything).
 *
 * The child class should implement at least:
 *  - getQueryOptions()
 *  - computeQueryParts()
 *  - getTableName() or add a static variable $table_name
 *
 * This class will provide:
 *  - getList()
 *  - getListForSelect() (default helper, can be overriden)
 *  - getBy()
 *  - getById() (default helper, can be overriden)
 */
class TableModel extends Model
{
	/**
	 * @var (string) the name of the table.
	 */
	static protected $table_name = '';

	/**
	 * @var (string) The last SQL query, for debug purposes
	 */
	static protected $last_select_query = null;

	/**
	 * Default getter for the table name. Can be updated in the child class.
	 * @return string
	 */
	static protected function getTableName()
	{
		if ( ! static::$table_name ) {
			throw new \LogicException(sprintf(
				'You forgot to implement %s::getTableName() or to set %1$s::$table_name',
				get_class()
			));
		}

		return static::$table_name;
	}

	/**
	 * Default getter for the query options array.
	 * Should be implemented in the child class
	 * @return array
	 */
	static protected function getQueryOptions()
	{
		return [
			'id' => null
		];
	}

	/**
	 * Default method to compute the query parts (where, join, select) based
	 * on the options array.
	 * Should be implemented in the child class
	 * @return null
	 */
	static protected function computeQueryParts($dbh, array & $opt, & $where, & $join, & $select)
	{
		$fields = ['id'];
		static::computeStandardWhereClause($dbh, $fields, $opt, $where);
	}

	/**
	 * Method that is executed after getList. Provides a hook to child class to
	 * alter the results, e.g. decode JSON fields.
	 */
	static protected function afterGetList($dbh, array $opt, & $list)
	{

	}

	/**
	 * Method that is executed after getBy. Provides a hook to child class to
	 * alter the results, e.g. decode JSON fields.
	 */
	static protected function afterGetBy($dbh, array $opt, & $obj)
	{
		$obj = new static($obj);
	}

///////////////////////////////////////////////////////////////////////////////
// Get functions (SELECT query)

	/**
	 * Method to do a select count(*).
	 *
	 * @param $opt (array) An array of options
	 * @return int
	 */
	static public function getCount(array $opt = array())
	{
		$opt = static::mergeOptions(static::getQueryOptions(), $opt);

		$dbh = Database::get();

		$where = array();
		$select = array();
		$join = array();

		static::computeQueryParts($dbh, $opt, $where, $join, $select);

		$where = implode(" AND ", $where);
		$join = implode(" \n ", $join);

		$table_name = static::getTableName();

		static::$last_select_query = sprintf(
			'SELECT COUNT(*)
			FROM %s t
			%s
			%s',
			$table_name,
			$join,
			$where ? 'WHERE '.$where : ''
		);
		
		return (int) $dbh->query(static::$last_select_query)->fetch(\PDO::FETCH_COLUMN);
	}

	/**
	 * Method to get a list of objects from the database.
	 *
	 * @param $opt (array) An array of options
	 * @return array|null
	 */
	static public function getList(array $opt = array())
	{
		// Note: by convention, the current table will always be aliased "t".
		// it's obviously not fool proof and will fail if you have a table named "t"...
		// Note 2: again by convention, the PK is "t.id"
		$opt = static::mergeOptions(array_merge([
			'pager' => null,
			'limit' => null,
			'group_by' => null,
			'order_by' => 'id',
			'select' => 't.id, t.*',
			'fetch_mode' => \PDO::FETCH_ASSOC | \PDO::FETCH_UNIQUE
		], static::getQueryOptions()), $opt);

		$dbh = Database::get();

		$where = array();
		$select = is_array($opt['select']) ? $opt['select'] : array($opt['select']);
		$join = array();

		if ( $opt['limit'] && ! static::isValidLimit($opt['limit']) ) {
			throw new \InvalidArgumentException("Malformed 'limit' option");
		}

		static::computeQueryParts($dbh, $opt, $where, $join, $select);

		$where = implode(" AND ", $where);
		$join = implode(" \n ", $join);
		$select = implode(",", $select);

		$table_name = static::getTableName();

		if ( $opt['pager'] ) {
			static::$last_select_query = sprintf(
				'SELECT COUNT(*)
				FROM %s t
				%s
				%s',
				$table_name,
				$join,
				$where ? 'WHERE '.$where : ''
			);
			$opt['pager']->queryForTotal($dbh, static::$last_select_query);
			if ( $opt['pager']->getCurrentPage(false) > $opt['pager']->getLastPage() ) {
				return null;
			}
			$opt['limit'] = $opt['pager']->getLimitClause();
		}

		static::$last_select_query = sprintf(
			'SELECT %s
			FROM %s t
			%s
			%s
			%s
			%s
			%s',
			$select,
			$table_name,
			$join,
			$where ? 'WHERE '.$where : '',
			$opt['group_by'] ? 'GROUP BY '.$opt['group_by'] : '',
			$opt['order_by'] ? 'ORDER BY '.$opt['order_by'] : '',
			$opt['limit'] ? 'LIMIT '.$opt['limit'] : ''
		);
		$ret = $dbh->query(static::$last_select_query);

		if ( $opt['fetch_mode'] ) {
			$ret = $ret->fetchAll($opt['fetch_mode']);
		}

		static::afterGetList($dbh, $opt, $ret);

		return $ret;
	}

	/**
	 * Return a assoc array with t.id => t.name
	 * This will not work for every time, and can be changed in child classes.
	 */
	static public function getListForSelect(array $opt = array())
	{
		if ( ! isset($opt['select']) ) {
			$opt['select'] = 't.id, t.name'; // default
		}

		$opt['fetch_mode'] = \PDO::FETCH_KEY_PAIR;

		return static::getList($opt);
	}

	/**
	 * Get one row from the table into an object.
	 * @return object|null An object of the current class
	 */
	static public function getBy(array $opt = array())
	{
		$opt = static::mergeOptions(static::getQueryOptions(), $opt);

		$dbh = Database::get();

		$where = array();
		$join = array();
		$select = array('t.*');

		static::computeQueryParts($dbh, $opt, $where, $join, $select);

		$select = implode(', ', $select);
		$join = implode(" \n ",$join);
		$where = implode(' AND ',$where);

		if ( ! $where ) {
			throw new \LogicException('The WHERE clause cannot be empty, you must specify how to get the item for getBy to work (hint: implement computeQueryParts())');
		}

		static::$last_select_query = sprintf(
			'SELECT %s
			FROM %s t
			%s
			WHERE %s',
			$select,
			static::getTableName(),
			$join,
			$where
		);

		$obj = $dbh->query(static::$last_select_query)->fetch(\PDO::FETCH_ASSOC);

		if ( ! $obj ) {
			return null;
		}

		static::afterGetBy($dbh, $opt, $obj);

		return $obj;
	}

	/**
	 * Get a row by ID
	 */
	static public function getById($id, array $opt = array())
	{
		if ( ! $id ) {
			return null;
		}
		return static::getBy(array_merge($opt, array(
			'id' => $id
		)));
	}

///////////////////////////////////////////////////////////////////////////////
// Compute functions (helpers)

	/**
	 * Helper that merges two arrays and throws an exception for unsupported keys,
	 * i.e. keys from array2 not in array1.
	 *
	 * @param $array1 (array)
	 * @param $array2 (array)
	 * @return array
	 */
	static public function mergeOptions(array $array1, array $array2)
	{
		$diff = array_diff_key($array2, $array1);
		if ( ! empty($diff) ) {
			throw new \InvalidArgumentException('Unsupported query options: '. implode(', ',array_keys($diff)));
		}

		return array_merge($array1, $array2);
	}

	/** 
	 * This method computes a fairly standard where clause for unique fields, 
	 * typically integer primary key and foreign keys.
	 *
	 * - a missing key, `null` or `false` will be ignored
	 * - an empty string or an empty array will produce "where field = ''"
	 * - the strings 'NULL' and 'NOT NULL' will produce "IS (NOT) NULL"
	 * - an array will produce "IN (...)"
	 * - a value will produce "= ..."
	 *
	 * @param $dbh (PDO)
	 * @param $fields (array) an array containing the name of the fields to compute
	 * @param $opt (array) an assoc array of options (field_name => value)
	 * @param $where (array) the array where the query parts will be added
	 * @return void
	 */
	static protected function computeStandardWhereClause($dbh, array $fields, array $opt, & $where)
	{
		foreach ( $fields as $field ) {
			// missing key, null or false are considered empty and thus ignored
			if ( ! isset($opt[$field]) || $opt[$field] === false ) {
				continue;
			}

			// is we pass an array, we produce a IN clause or a BETWEEN clause
			if ( is_array($opt[$field]) ) {

				$key = key($opt[$field]);
				if ( $key === 0 || $key === null ) {
					static::computeIn($dbh, $field, $opt[$field], $where, true);
					continue;
				}

				switch ( $key ) {
					case 'eq':
						static::computeEq($dbh, $field, $opt[$field]['eq'], $where);
					break;
					case 'neq':
						static::computeNeq($dbh, $field, $opt[$field]['neq'], $where);
					break;
					case 'lt':
						static::computeLt($dbh, $field, $opt[$field]['lt'], $where);
					break;
					case 'lte':
						static::computeLte($dbh, $field, $opt[$field]['lte'], $where);
					break;
					case 'gt':
						static::computeGt($dbh, $field, $opt[$field]['gt'], $where);
					break;
					case 'gte':
						static::computeGte($dbh, $field, $opt[$field]['gte'], $where);
					break;
					case 'in':
						static::computeIn($dbh, $field, $opt[$field]['in'], $where, false);
					break;
					case 'between':
						static::computeBetween($dbh, $field, $opt[$field]['between'], $where);
					break;
					case 'is':
						static::computeIs($dbh, $field, $opt[$field]['is'], $where);
					break;
					case 'isnt':
						static::computeIsnt($dbh, $field, $opt[$field]['isnt'], $where);
					break;
					default:
						throw new \InvalidArgumentException('Unknown where operator: '.$key);
				}
			} else {
				static::computeEq($dbh, $field, $opt[$field], $where);
			}
		}
	}

	static protected function computeEq($dbh, $field, $value, array &$where)
	{
		if ( $value === 'NULL' || $value === 'NOT NULL') {
			$where[] = sprintf(
				't.%s IS %s',
				$field,
				$value
			);
		}
		else {
			$where[] = sprintf(
				't.%s = %s',
				$field,
				$dbh->quote($value)
			);
		}
	}

	static protected function computeNeq($dbh, $field, $value, array &$where)
	{
		$where[] = sprintf(
			't.%s != %s',
			$field,
			$dbh->quote($value)
		);
	}

	static protected function computeLt($dbh, $field, $value, array &$where)
	{
		$where[] = sprintf(
			't.%s < %s',
			$field,
			$dbh->quote($value)
		);
	}

	static protected function computeLte($dbh, $field, $value, array &$where)
	{
		$where[] = sprintf(
			't.%s <= %s',
			$field,
			$dbh->quote($value)
		);
	}

	static protected function computeGt($dbh, $field, $value, array &$where)
	{
		$where[] = sprintf(
			't.%s > %s',
			$field,
			$dbh->quote($value)
		);
	}

	static protected function computeGte($dbh, $field, $value, array &$where)
	{
		$where[] = sprintf(
			't.%s >= %s',
			$field,
			$dbh->quote($value)
		);
	}

	static protected function computeIs($dbh, $field, $value, array &$where)
	{
		if ( $value != 'null' && $value != 'NULL' ) {
			throw new \InvalidArgumentException('Invalid value for "is" operator');
		}

		$where[] = sprintf(
			't.%s IS NULL',
			$field
		);
	}

	static protected function computeIsnt($dbh, $field, $value, array &$where)
	{
		if ( $value != 'null' && $value != 'NULL' ) {
			throw new \InvalidArgumentException('Invalid value for "isnt" operator');
		}

		$where[] = sprintf(
			't.%s IS NOT NULL',
			$field
		);
	}

	static protected function computeBetween($dbh, $field, $value, array &$where)
	{
		if ( ! is_array($value) || count($value) != 2 || ! array_key_exists(0,$value) || ! array_key_exists(1,$value) ) {
			throw new \InvalidArgumentException('Between clause array must have exactly 2 rows');
		}
		list($min, $max) = $value;
		if ( $min !== null && $max !== null ) {
			$where[] = sprintf(
				't.%s BETWEEN %s AND %s',
				$field,
				$dbh->quote($min),
				$dbh->quote($max)
			);
		}
		elseif ( $min !== null ) {
			$where[] = sprintf(
				't.%s >= %s',
				$field,
				$dbh->quote($min)
			);
		}
		elseif ( $max !== null ) {
			$where[] = sprintf(
				't.%s <= %s',
				$field,
				$dbh->quote($max)
			);
		}
		else {
			// max AND min are null, nothing to do
		}
	}

	static protected function computeIn($dbh, $field, $value, array &$where, $reduce_to_equal = false)
	{
		if ( ! is_array($value) ) {
			$value = explode(',',$value);
		}

		if ( empty($value) ) {
			// empty array produces empty SQL clause
			$where[] = sprintf(
				"t.%s = ''",
				$field
			);
		} elseif ( sizeof($value) === 1 && $reduce_to_equal ) {
			// compute as an equal
			static::computeEq($dbh, $field, array_pop($value), $where);
		} else {
			$or_null = false;
			$in = [];
			foreach ( $value as $v ) {
				if ( $v === null || $v === false ) {
					continue;
				}
				if ( $v === 'NULL' ) {
					$or_null = true;
					continue;
				}
				$in[] = $dbh->quote($v);
			}

			if ( empty($in) ) {
				$where[] = sprintf(
					"t.%s = ''",
					$field
				);
				return;
			}

			$sql = [];
			if ( ! empty($in) ) {
				$sql[] = sprintf(
					't.%s IN (%s)',
					$field,
					implode(',',$in)
				);
			}

			if ( $or_null ) {
				$sql[] = sprintf(
					't.%s IS NULL',
					$field
				);
			}

			if ( isset($sql[1]) ) {
				$where[] = '('.implode(' OR ',$sql).')';
			} else {
				$where[] = $sql[0];
			}
		}
	}

	static public function isValidLimit($limit)
	{
		return ! $limit || preg_match('/^\d+( ?, ?\d+)?$/', $limit);
	}

	/**
	 * Convert any date into a DateTime object.
	 * @throws InvalidArgumentException on error
	 * @param mixed $date
	 * @return DateTime
	 */
	static public function parseDate($date)
	{
		if ( $date === null || $date === '' ) {
			throw new \InvalidArgumentException(
				"Failed to parse the date - no date was given"
			);
		}

		// DateTimeInterface is only on PHP 5.5+, and includes DateTimeImmutable
		if ( ! $date instanceof \DateTime && ! $date instanceof \DateTimeInterface ) {
			try {
				if ( is_integer($date) ) {
					$date = \DateTime::createFromFormat('U',$date);
				}
				else {
					$date = new \DateTime($date);
				}
			} catch (\Exception $e) {
				throw new \InvalidArgumentException(
					"Failed to parse the date - invalid format"
				);
			}
		}
		else {
			$date = clone $date; // avoid reference problems
		}
		return $date;
	}

	/**
	 * Decode a JSON-encoded array/object into a assoc array.
	 *
	 * @param $json (string) The JSON-encoded array
	 * @param $default (array) The default array. The JSON will be merged with
	 *                         this array in order to construct the final result.
	 * @param $strict bool    Removes extraneous keys
	 * @return array
	 */
	static public function parseJson($json, array $default = null, $strict = false)
	{
		if ( is_string($json) ) {
			if ( ! $json ) {
				$json = array();
			}
			else {
				$json = json_decode($json, true);
				if ( $json === null ) {
					if ( ($error = json_last_error()) !== JSON_ERROR_NONE ) {
						// invalid JSON
						throw new \InvalidArgumentException("Invalid JSON (error #$error)");
					}
					else {
						$json = array();
					}
				}
				elseif ( is_string($json) ) {
					// JSON is valid but resulted in a string
					// I'm not sure if we should throw an exception, or cast it,
					// or ignore it and return a string
					throw new \InvalidArgumentException('JSON-encoded array or object expected, JSON-encoded string provided');
				}
			}
		}
		elseif ( is_object($json) ) {
			$json = (array) $json;
		}
		elseif ( $json === null ) {
			$json = array();
		}

		if ( $default !== null ) {
			$json = array_replace_recursive($default, $json);
			if ( $strict ) {
				$json = array_intersect_key($json, $default);
			}
		}

		return $json;
	}

///////////////////////////////////////////////////////////////////////////////
// Log

	static public function getLastSelectQuery()
	{
		return str_replace(["\t"],[''], static::$last_select_query);
	}
}
