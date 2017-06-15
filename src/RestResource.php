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

abstract class RestResource
{
	const ALL = true;
	const NONE = false;

	protected $model_filters = [];

	/**
	 * Implement to return an array with the allowed sort options, and their
	 * translation is SQL.
	 *
	 * Example:
	 * return ['id' => 't.id'];
	 *
	 * @return array
	 */
	abstract public function getAllowedSort();

	/**
	 * Implement to return an array with the allowed filter and their operators
	 *
	 * Example:
	 * return ['id' => ['eq','neq','in']]
	 *
	 * @return array
	 */
	abstract public function getAllowedFilters();

	/**
	 * Implement to return the max number of entities per page
	 * @return int
	 */
	abstract public function getMaxPerPage();

	/**
	 * Return the default value for a request. Override if necessary.
	 * @return array
	 */
	public function getDefaultRequest()
	{
		return [
			'sort' => '',
			'page' => 1,
			'per_page' => 200
		];
	}

	/**
	 * Process a request, represented as an array of parameters
	 *
	 * @param $request array 
	 * @throws \InvalidArgumentException
	 */
	public function processRequest(array $request)
	{
		$request = array_merge($this->getDefaultRequest(), $request);

		$this->model_filters['order_by'] = $this->processSort($request['sort'], $this->getAllowedSort());
		unset($request['sort']);

		$this->model_filters['pager'] = $this->processPagination($request['page'], $request['per_page'], $this->getMaxPerPage());
		unset($request['per_page']);
		unset($request['page']);

		$this->model_filters = array_merge($this->model_filters, $this->processFilters($request, $this->getAllowedFilters()));
	}

	/**
	 * Process the sort option
	 */
	protected function processSort($sort, array $sortable_fields)
	{
		if ( ! $sort ) {
			return null;
		}

		$order_by = array();
		$invalid_fields = array();

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

	/**
	 * Process the filters
	 */
	protected function processFilters(array $request, array $allowed_filters)
	{
		$results = [];

		foreach ( $request as $filter => $value ) {
			if ( ! array_key_exists($filter, $allowed_filters) ) {
				throw new \InvalidArgumentException("Invalid filter '$filter'");
			}

			if ( $allowed_filters[$filter] == self::NONE ) {
				$results[$filter] = $value;
			} else {
				$pos = strpos($value, ':');
				if ( $pos === false ) {
					throw new \InvalidArgumentException("Invalid filter '$filter' operator required");
				}

				$operator = substr($value, 0, $pos);
				$value = substr($value, $pos+1);

				// check if operator is allowed
				if ( $allowed_filters[$filter] !== self::ALL && ! in_array($operator, $allowed_filters[$filter]) ) {
					throw new \InvalidArgumentException("Invalid operator '$operator' for filter '$filter'");
				}

				$results[$filter] = [$operator => $value];
			}
		}

		return $results;
	}

	/**
	 * Process the pagination related options
	 */
	protected function processPagination($page, $per_page, $max_per_page)
	{
		if ( $per_page > $max_per_page ) {
			throw new \InvalidArgumentException("Invalid per_pare (maximum is $max_per_page)");
		}

		if ( $page < 1 ) {
			throw new \InvalidArgumentException("Invalid page (minimum 1)");
		}

		return new \DbTools\Pager($per_page, $page);
	}

	/**
	 * Return the processed options for the TableModel class
	 */
	public function getModelFilters()
	{
		return $this->model_filters;
	}

	/**
	 * Format a collection
	 */
	public function formatCollection($collection)
	{
		if ( $collection !== null && ! is_array($collection) ) {
			throw new \InvalidArgumentException('Collection must be an array (or null)');
		}

		$results = [
			'success' => true,
			'page' => $this->model_filters['pager']->getCurrentPage(),
			'per_page' =>  $this->model_filters['pager']->getPerPage(),
			'total' =>  $this->model_filters['pager']->getTotal(),
			'nb_pages' =>  $this->model_filters['pager']->getNbPages(),
			'data' => []
		];

		if ( $collection !== null ) {
			foreach ( $collection as $entity ) {
				$results['data'][] = $this->formatEntity($entity);
			}
		}

		return $results;
	}

	/**
	 * Format an entity. Implement in the child class.
	 */
	abstract public function formatEntity($entity);

	/**
	 * Helper
	 */
	public function formatDate($date)
	{
		if ( ! $date ) {
			return null;
		}

		return date_create($date)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
	}
}