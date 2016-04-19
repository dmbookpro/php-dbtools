<?php

/**
 * Licensed under the MIT license.
 *
 * For the full copyright and license information, please view the LICENSE file.
 *
 * @author Rémi Lanvin <remi@cloudconnected.fr>
 * @link https://github.com/rlanvin/php-dbtools
 */

namespace DbTools;

/**
 * This class regroups helper functions for Model classes to work with a Table
 * (typically MySQL, although in theory it should work for everything).
 *
 * The child class should implement at least:
 *  - getQueryOptions()
 *  - computeQueryParts()
 *  - getTableName() (or add a static variable $table_name)
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
	static protected function computeQueryParts($dbh, array $opt, & $where, & $join, & $select)
	{
		$fields = ['id'];
		static::computeStandardWhereClause($dbh, $fields, $opt, $where);
	}

	static protected function afterGetList($dbh, array $opt, & $list)
	{

	}

	static protected function afterGetBy($dbh, array $opt, & $obj)
	{

	}

///////////////////////////////////////////////////////////////////////////////
// Get functions (SELECT query)

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
		$opt = self::mergeOptions(array_merge([
			'pager' => null,
			'order_by' => 'id',
			'select' => 't.id, t.*',
			'fetch_mode' => \PDO::FETCH_ASSOC | \PDO::FETCH_UNIQUE
		], static::getQueryOptions()), $opt);

		$dbh = Database::get();

		$where = array();
		$select = is_array($opt['select']) ? $opt['select'] : array($opt['select']);
		$join = array();

		static::computeQueryParts($dbh, $opt, $where, $join, $select);

		$where = implode(" AND ", $where);
		$join = implode(" \n ", $join);
		$select = implode(",", $select);

		$table_name = static::getTableName();

		if ( $opt['pager'] ) {
			$sql = sprintf(
				'SELECT COUNT(*)
				FROM %s t
				%s
				%s',
				$table_name,
				$join,
				$where ? 'WHERE '.$where : ''
			);
			$opt['pager']->queryForTotal($dbh, $sql);
			if ( $opt['pager']->getCurrentPage(false) > $opt['pager']->getLastPage() ) {
				return null;
			}
		}

		$sql = sprintf(
			'SELECT %s
			FROM %s t
			%s
			%s
			%s
			%s',
			$select,
			$table_name,
			$join,
			$where ? 'WHERE '.$where : '',
			$opt['order_by'] ? 'ORDER BY '.$opt['order_by'] : '',
			$opt['pager']? 'LIMIT '.$opt['pager']->getLimitClause() : ''
		);
		$ret = $dbh->query($sql);

		if ( ! $opt['fetch_mode'] ) {
			return $result;
		}

		$list = $ret->fetchAll($opt['fetch_mode']);

		static::afterGetList($dbh, $opt, $list);

		return $list;
	}

	static public function getListForSelect(array $opt = array())
	{
		if ( ! isset($opt['select']) ) {
			$opt['select'] = 't.id, t.name'; // default
		}

		$opt['fetch_mode'] = \PDO::FETCH_KEY_PAIR;

		return static::getList($opt);
	}

	static public function getBy(array $opt = array())
	{
		$opt = self::mergeOptions(static::getQueryOptions(), $opt);

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

		$sql = sprintf(
			'SELECT %s
			FROM %s t
			%s
			WHERE %s',
			$select,
			static::getTableName(),
			$join,
			$where
		);

		$obj = $dbh->query($sql)->fetch(\PDO::FETCH_ASSOC);

		if ( ! $obj ) {
			return null;
		}

		$obj = new static($obj);

		static::afterGetBy($dbh, $opt, $obj);

		return $obj;
	}

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
				// BETWEEN
				if ( isset($opt[$field]['between']) && is_array($opt[$field]['between']) ) {
					if ( count($opt[$field]['between']) != 2 || ! array_key_exists(0,$opt[$field]['between']) || ! array_key_exists(1,$opt[$field]['between']) ) {
						throw new \InvalidArgumentException('Between clause array must have exactly 2 rows');
					}
					list($min, $max) = $opt[$field]['between'];
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
						// what do you expect me to do here?
					}
					continue;
				}
				// IN
				else {
					if ( empty($opt[$field]) ) {
						// empty array produces empty SQL clause
						$where[] = sprintf(
							"t.%s = ''",
							$field
						);
						continue;
					}
					elseif ( sizeof($opt[$field]) === 1 ) {
						$opt[$field] = array_pop($opt[$field]);
						// compute as if it wasn't an array
					}
					else {
						$or_null = false;
						$in = [];
						foreach ( $opt[$field] as $value ) {
							if ( $value === null || $value === false ) {
								continue;
							}
							if ( $value === 'NULL' ) {
								$or_null = true;
								continue;
							}
							$in[] = $dbh->quote($value);
						}
						if ( empty($in) ) {
							$where[] = sprintf(
								"t.%s = ''",
								$field
							);
							continue;
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
						}
						else {
							$where[] = $sql[0];
						}
						continue;
					}
				}
			}

			if ( $opt[$field] === 'NULL' || $opt[$field] === 'NOT NULL') {
				$where[] = sprintf(
					't.%s IS %s',
					$field,
					$opt[$field]
				);
			}
			else {
				$where[] = sprintf(
					't.%s = %s',
					$field,
					$dbh->quote($opt[$field])
				);
			}
		}
	}

	static public function mergeOptions(array $array1, array $array2)
	{
		$diff = array_diff_key($array2, $array1);
		if ( ! empty($diff) ) {
			throw new \InvalidArgumentException('Unsupported query options: '. implode(', ',array_keys($diff)));
		}

		return array_merge($array1, $array2);
	}

	/**
	 * Decode a JSON-encoded array/object into a assoc array.
	 *
	 * @param $json (string) The JSON-encoded array
	 * @param $default (array) The default array. The JSON will be merged with
	 *                         this array in order to construct the final result.
	 * @return array
	 */
	static public function parseJson($json, array $default = null)
	{
		if ( is_string($json) ) {
			if ( ! $json ) {
				$json = array();
			}
			else {
				$json = json_decode($json, true);
				if ( $json === null ) {
					// invalid JSON
					throw new \InvalidArgumentException("Invalid JSON");
				}
				elseif ( is_string($json) ) {
					// JSON is valid but resulted in a string
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
			$json = array_merge($default, $json);
		}

		return $json;
	}
}
