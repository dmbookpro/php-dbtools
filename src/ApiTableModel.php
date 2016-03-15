<?php

class ApiTableModel extends TableModel
{

	static public function getFieldsForApi()
	{
		return [
			'id' => null,
		];
	}

	static public function getQueryOptionsForApi()
	{
		return [
			'fields' => null,
			'sort' => null,
			'per_page' => 200,
			'page' => 1,
			'pagination_meta' => true,
			'embed' => null,
		];
	}

	/**
	 * Convert a "fields" option to a "select" option.
	 *
	 * Example: "id,name" will be converted in ['t.id', 't.name']
	 *
	 * @param string $fields
	 * @return array
	 */
	static private function convertFieldsToSelect($fields)
	{
		$api_fields = static::getFieldsForApi();

		if ( $fields ) {
			if ( ! is_array($fields) ) {
				$fields = explode(',',$fields);
			}
			$diff = array_diff($fields, array_keys($api_fields));
			if ( ! empty($diff) ) {
				throw new \ApiTableModelException('Unknown fields: '.implode(', ',$diff));
			}
		}
		else {
			$fields = array_keys($api_fields);
		}

		$select = ['t.id']; // the key of the array (for PDO::FETCH_UNIQUE)

		foreach ( $fields as $key ) {
			$select[] = 't.'.$key;
		}

		return $select;
	}

	/**
	 * Convert a "sort" option to a "order_by" option.
	 *
	 * Example: "id,-name" will be converted in "t.id ASC,t.name DESC"
	 *
	 * @param string $sort
	 * @return string
	 */
	static private function convertSortToOrderBy($sort)
	{
		if ( empty($sort) ) {
			return null;
		}

		$order_by = array();
		$invalid_fields = array();
		$api_fields = static::getFieldsForApi();

		if ( ! is_array($sort) ) {
			$sort = explode(',',$sort);
		}

		foreach ( $sort as $field ) {
			if ( ! $field ) {
				$invalid_fields[] = $field;
				continue;
			}

			$direction = 'ASC';
			if ( $field[0] === '-' ) {
				$direction = 'DESC';
				$field = substr($field,1);
			}
			elseif ( $field[0] === '+' ) {
				$field = substr($field,1);
			}

			if ( ! array_key_exists($field, $api_fields) ) {
				$invalid_fields[] = $field;
				continue;
			}
			$order_by[] = 't.'.$field.' '.$direction;
		}

		if ( ! empty($invalid_fields) ) {
			throw new \ApiTableModelException('Unknown fields in sort: '.implode(', ',$invalid_fields));
		}

		return implode(',',$order_by);
	}

	static private function convertEmbedToFetch($embed)
	{
		$opt = array();

		if ( $embed ) {
			$options = static::getQueryOptions();
			$invalid_objects = [];
			foreach ( explode(',',$embed) as $object ) {
				if ( ! array_key_exists('embed_'.$object, $options) ) {
					$invalid_objects[] = $object;
					continue;
				}
				$opt['embed_'.$object] = true;
			}
			if ( ! empty($invalid_objects) ) {
				throw new \ApiTableModelException('Unknown objects in embed: '.implode(', ',$invalid_objects));
			}
		}

		return $opt;
	}

	/**
	 * Get list of items, with support for the API options (fields, sort, etc.)
	 */
	static public function getListForApi(array $opt = array())
	{
		$default_api_opt = static::getQueryOptionsForApi();
		$opt = array_merge($default_api_opt, $opt); // at this point we accept extraneous options

		// pagination (LIMIT clause)
		// should we validate some minimum, maximums ?
		if ( $opt['per_page'] ) {
			$opt['pager'] = new \Pager($opt['per_page'], $opt['page']);
		}

		// fields (SELECT clause)
		$opt['select'] = self::convertFieldsToSelect($opt['fields']);

		// sort (ORDER BY clause)
		$opt['order_by'] = self::convertSortToOrderBy($opt['sort']);

		// embed
		if ( $opt['embed'] ) {
			$opt = array_merge($opt, self::convertEmbedToFetch($opt['embed']));
		}

		$opt['fetch_mode'] = \PDO::FETCH_OBJ | \PDO::FETCH_UNIQUE; // enforce this fetch mode
		$list = static::getList(
			array_diff_key($opt, $default_api_opt) // remove options from the API
		);

		$list = array_values($list); // remove the id as key (we won't need it anymore)

		// pagination meta
		if ( $opt['pagination_meta'] ) {
			$list = [
				'page' => $opt['pager']->getCurrentPage(),
				'per_page' => $opt['pager']->getPerPage(),
				'total' => $opt['pager']->getTotal(),
				'nb_pages' => $opt['pager']->getNbPages(),
				'data' => $list
			];
		}

		return $list;
	}

	/**
	 * Only supports embed (the rest is ignored)
	 */
	public function getValuesForApi(array $opt = array())
	{
		$default_api_opt = static::getQueryOptionsForApi();
		$opt = array_merge($default_api_opt, $opt);

		if ( $opt['embed'] ) {
			$opt = array_merge($opt, self::convertEmbedToFetch($opt['embed']));
		}

		// fetch all the embedded fields
		$opt2 = static::mergeOptions(static::getQueryOptions(), array_diff_key($opt, $default_api_opt));
		$dbh = Database::get();
		static::afterGetBy($dbh, $opt2, $this);

		$values = $this->getValues();

		// only keep the public fields
		$api_fields = static::getFieldsForApi();
		$filtered_values = (object) array_intersect_key($values, $api_fields);

		// add the embedded fields (which have been validated before)
		if ( $opt['embed'] ) {
			foreach ( explode(',',$opt['embed']) as $field ) {
				$filtered_values->$field = $this->{$field};
			}
		}

		return $filtered_values;
	}

	static public function mergeOptions(array $array1, array $array2)
	{
		try {
			return parent::mergeOptions($array1, $array2);
		} catch ( \InvalidArgumentException $e ) {
			throw new \ApiTableModelException($e->getMessage(), 0, $e);
		}
	}
}
