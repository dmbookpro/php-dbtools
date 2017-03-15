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
 * Pager for lists.
 */
class Pager
{
	/**
	 * @var int Total number of items
	 */
	protected $total = 0;

	/**
	 * @var int Total number of items par page
	 */
	protected $per_page = 0;

	/**
	 * @var int Current page number
	 */
	protected $current_page = 1;

	/**
	 * @var int total number of pages (once it has been computed)
	 */
	protected $nb_pages = 0;

	/**
	 * Constructor
	 *
	 * @param $per_page (int) Number of items par page
	 * @param $current_page (int) Current page
	 */
	public function __construct($per_page = 20, $current_page = 1)
	{
		$this->setPerPage($per_page);
		$this->setCurrentPage($current_page);
	}

	/**
	 * Set the current page
	 *
	 * @param $current_page (int) Pass negative value to start counting from the last (-1 is the last page)
	 * @throws \InvalidArgumentException
	 * @return $this
	 */
	public function setCurrentPage($current_page)
	{
		if ( ! is_numeric($current_page) || (int) $current_page === 0 ) {
			throw new \InvalidArgumentException('Invalid current page value');
		}

		$this->current_page = (int) $current_page;
		return $this;
	}

	/**
	 * Return current page number
	 *
	 * @param $computed (bool) Whether to return the computed current page, or the raw value (default true)
	 * @return int
	 */
	public function getCurrentPage($computed = true)
	{
		if ( ! $computed ) {
			return $this->current_page;
		}

		if ( $this->current_page < 0 ) {
			return $this->getLastPage() + $this->current_page + 1;
		}

		return min($this->getLastPage(), $this->current_page);
	}

	/**
	 * Set the number of items par page
	 *
	 * @param $per_page (int)
	 * @throws \InvalidArgumentException
	 * @return $this
	 */
	public function setPerPage($per_page)
	{
		if ( ! is_numeric($per_page) || (int) $per_page < 1 ) {
			throw new \InvalidArgumentException('Invalid number of items per page');
		}

		$this->nb_pages = 0;
		$this->per_page = (int) $per_page;
		return $this;
	}

	/**
	 * Returns the number of elements per page
	 *
	 * @return int
	 */
	public function getPerPage()
	{
		return $this->per_page;
	}

	/**
	 * Set total number of items.
	 *
	 * @param int $total
	 * @return $this;
	 */
	public function setTotal($total)
	{
		if ( ! is_numeric($total) || (int) $total < 0 ) {
			throw new \InvalidArgumentException(sprintf(
				'Invalid total (%s)',
				$total
			));
		}

		$this->nb_pages = 0;
		$this->total = (int) $total;
		return $this;
	}

	/**
	 * Return the total number of items.
	 *
	 * @return int
	 */
	public function getTotal()
	{
		return $this->total;
	}

//////////////////////////////////////////////////////////////////////////////
// Database helpers

	/**
	 * Helper to the total from the result of a SQL query
	 */
	public function queryForTotal($dbh_or_statement, $sql = null)
	{
		$total = 0;

		if ( $sql !== null ) {
			$total = (int) $dbh_or_statement->query($sql)->fetch(\PDO::FETCH_COLUMN);
		}
		elseif ( ($dbh_or_statement instanceof \PDOStatement) || is_callable([$dbh_or_statement, 'execute']) ) {
			$dbh_or_statement->execute();
			$total = (int) $dbh_or_statement->fetch(\PDO::FETCH_COLUMN);
		}
		else {
			throw new \InvalidArgumentException(sprintf(
				'Invalid parameters, must be PDOStatement or PDO + query, %s given',
				get_class($dbh_or_statement)
			));
		}

		return $this->setTotal($total);
	}

	/**
	 * Returns SQL limit clause
	 *
	 * Example:
	 * $sql = 'SELECT * FROM truc LIMIT '.$pager->getLimitClause()
	 *
	 * @return string
	 */
	public function getLimitClause()
	{
		return $this->getOffset().','.$this->per_page;
	}

	/**
	 * Returns offset for SQL limit clause
	 */
	public function getOffset()
	{
		return $this->per_page * ($this->getCurrentPage() - 1);
	}

//////////////////////////////////////////////////////////////////////////////
// Navigation helpers (next, previous, etc.)

	/**
	 * Tells if pagination is necessary (i.e. if elements don't fit on one page)
	 *
	 * @return bool
	 */
	public function haveToPaginate()
	{
		return ($this->total > $this->per_page);
	}

	/**
	 * Returns previous page number, or 1 if it's already the first page.
	 *
	 * @return int
	 */
	public function getPreviousPage()
	{
		$current_page = $this->getCurrentPage();
		return $current_page === 1 ? 1 : $current_page - 1;
	}

	/**
	 * Returns next page number, or current if it's already the last page
	 *
	 * @return int
	 */
	public function getNextPage()
	{
		$current_page = $this->getCurrentPage();
		$last_page = $this->getLastPage();
		return $current_page >= $last_page ? $last_page : $current_page + 1;
	}

	/**
	 * Returns the total number of pages
	 *
	 * @see getLastPage()
	 * @return int
	 */
	public function getNbPages()
	{
		return $this->getLastPage();
	}

	/**
	 * Returns the number of the last page (which is also the total number of pages)
	 *
	 * @return int
	 */
	public function getLastPage()
	{
		if ( ! $this->nb_pages ) {
			$this->nb_pages = $this->total === 0 ? 1 : (int) ceil($this->total / $this->per_page);
		}

		return $this->nb_pages;
	}

	/**
	 * Returns an array of pages before and after current page.
	 *
	 * @param $before  (int)  Number of pages before the current one
	 * @param $after   (int)  Number of pages after the current one
	 *                        (same as $before if not provided)
	 * @return array
	 */
	public function getPagesBeforeAndAfter($before = 3, $after = null)
	{
		if ( ! is_numeric($before) || (int) $before < 0 ) {
			throw new \InvalidArgumentException('Invalid number of pages');
		}

		if ( $after === null ) {
			$after = $before;
		}
		elseif ( ! is_numeric($after) || (int) $after < 0 ) {
			throw new \InvalidArgumentException('Invalid number of pages');
		}

		$current_page = $this->getCurrentPage();
		$last_page = $this->getLastPage();

		// what is the first page (lower bound)
		$low = $current_page - $before;
		if ( $low < 1 ) {
			$low = 1;
		}

		// what is the last page (higher bound)
		$high = $current_page + $after;
		if ( $high > $last_page ) {
			$high = $last_page;
		}

		return range($low, $high);
	}
}
