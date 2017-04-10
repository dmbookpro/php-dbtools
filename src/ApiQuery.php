<?php

namespace DbTools;

/**
 * This class filter query parameters from an API query
 */
class ApiQuery
{
	/**
	 * @var array The original query
	 */
	protected $query = [];

	/**
	 * @var array The parsed and validated options
	 */
	protected $opt = [];

	public function getPublicFields()
	{
		return [
			'id' => null
		];
	}

	public function getSortableFields()
	{
		return [
			'id' => 'id'
		];
	}

	public function getDefaultQueryOptions()
	{
		return [
			'fields' => null,
			'sort' => null,
			'per_page' => 200,
			'page' => 1,
			'pagination_meta' => true,
			'embed' => null
		];
	}

	public function __construct(array $query = [])
	{
		$default = $this->getDefaultQueryOptions();

		// test extraneous
		if ( $diff = array_diff_key($query, $default) ) {
			throw new \InvalidArgumentException("Unknown query option(s): ".implode(',',array_keys($diff)));
		}

		$this->query = array_replace($default, $query);

		// now test each

		$this->opt = [];

		if ( $this->query['sort'] ) {
			$this->opt['order_by'] = $this->processSort();
		}

		if ( $this->query['per_page'] ) {
			$this->opt['pager'] = $this->processPager();
		}

		$this->processFields();
	}


	protected function processFields()
	{
		$public_fields = $this->getPublicFields();

		if ( ! $this->query['fields'] ) {
			$this->query['fields'] = array_keys($public_fields);
		}

		if ( ! is_array($this->query['fields']) ) {
			$this->query['fields'] = explode(',',$this->query['fields']);
		}
		$diff = array_diff($this->query['fields'], array_keys($public_fields));
		if ( ! empty($diff) ) {
			throw new \InvalidArgumentException('Unknown fields: '.implode(', ',$diff));
		}

		$this->query['fields'] = array_intersect_key($public_fields, array_flip($this->query['fields']));

		return $this;
	}

	protected function processSort()
	{
		$sort = $this->query['sort'];
		if ( ! $sort ) {
			return null;
		}

		$order_by = array();
		$invalid_fields = array();
		$sortable_fields = $this->getSortableFields();

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

			if ( ! array_key_exists($field, $sortable_fields) ) {
				$invalid_fields[] = $field;
				continue;
			}
			$order_by[] = $sortable_fields[$field].' '.$direction;
		}

		if ( ! empty($invalid_fields) ) {
			throw new \InvalidArgumentException('Unknown sort option: '.implode(', ',$invalid_fields));
		}

		return implode(',',$order_by);
	}

	protected function processPager()
	{
		// test pagination meta

		// test per_page

		// test page

		return new Pager($this->query['per_page'], $this->query['page']);
	}

	public function getListQueryOptions()
	{
		return $this->opt;
	}

	/**
	 * Take a list a format it for the API
	 */
	public function formatList(array $list)
	{
		$result = [];

		foreach ( $list as $values ) {
			$values = $this->formatValues($values);
			$result[] = $values;
		}

		$result = ['data' => $result];

		if ( $this->query['pagination_meta'] ) {
			$result = array_merge([
				'page' => $this->opt['pager']->getCurrentPage(),
				'per_page' =>  $this->opt['pager']->getPerPage(),
				'total' =>  $this->opt['pager']->getTotal(),
				'nb_pages' =>  $this->opt['pager']->getNbPages(),
			], $result);
		}

		return $result;
	}

	/**
	 * Take an array of values and format it for the API.
	 */
	public function formatValues(array $values, array $formatters = null)
	{
		$formatted_values = array();

		if ( $formatters === null ) {
			$formatters = $this->query['fields'];
		}

		foreach ( $formatters as $name => $formatter ) {
			if ( ! array_key_exists($name, $values) ) {
				continue;
			}

			$value = $values[$name];
			if ( is_array($formatter) && is_array($value) ) {
				if ( sizeof($formatter) == 1 && isset($formatter[0]) ) {
					$value = [];
					foreach ( $values[$name] as $v ) {
						$value[] = $this->formatValues($v, $formatter[0]);
					}
				}
				else {
					$value = $this->formatValues($value, $formatter);
				}
			}
			else {
				switch ( $formatter ) {
					case 'datetime':
						if ( $value ) {
							$value = date_create($value)->setTimeZone(new \DateTimeZone('GMT'))->format('Y-m-d\TH:i:s\Z');
						}
					break;
					case 'bool': 
						if ( $value !== null ) {
							$value = !! $value;
						}
					break;
				}
			}
			$formatted_values[$name] = $value;
		}

		return $formatted_values;
	}
}