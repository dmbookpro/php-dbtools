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

use \PDO;

/**
 * Database connection handler.
 *
 * This is a global factory class for PDO instances.
 */
class Database
{
	/**
	 * @var array Static assoc array to store all the handlers.
	 */
	static protected $handlers = array();

	/**
	 * @var array
	 */
	static protected $config = array();

	static public function setConfig(array $config)
	{
		if ( ! isset($config['default']) ) {
			throw new \InvalidArgumentException('"default" database handler configuration is missing');
		}

		// reset config and handlers
		self::$config = array();
		self::deleteHandlers();

		foreach ( $config as $handler => $c ) {
			self::$config[$handler] = array_replace_recursive(array(
				'class' => 'PDO',
				'dsn' => '',
				'user' => '',
				'password' => '',
				'options' => array(),
				'attributes' => array(
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
				)
			), $c);
		}
	}

	static public function getConfig($handler = null)
	{
		if ( ! $handler ) {
			return self::$config;
		}

		if ( ! isset(self::$config[$handler]) ) {
			throw new \InvalidArgumentException("Configuration for handler $handler not found - has the config been initialized correctly?");
		}

		return self::$config[$handler];
	}

	/**
	 * Creates a PDO handler based on the config array
	 */
	static public function createPDO(array $config)
	{
		$classname = isset($config['class']) ? $config['class'] : 'PDO';

		$dbh = new $classname($config['dsn'], $config['user'], $config['password'], $config['options']);

		foreach ( $config['attributes'] as $key => $value ) {
			$dbh->setAttribute($key, $value);
		}

		return $dbh;
	}

	/**
	 * Returns a PDO handler identified by $id.
	 *
	 * @param $id (string) The identifier in the config file
	 * @param $database (string) Optional parameter, will replace a "%s" in the DSN.
	 * @return PDO
	 */
	static public function get($id = 'default', $database = '')
	{
		if ( ! isset(self::$handlers[$id]) ) {
			$config = self::getConfig($id);

			$config['dsn'] = sprintf($config['dsn'], $database);

			self::$handlers[$id] = self::createPDO($config);
		}

		return self::$handlers[$id];
	}

	/**
	 * Explicit naming for get() (also backward compatible)
	 */
	static public function getHandler($id = 'default', $database = '')
	{
		return self::get($id, $database);
	}

	/**
	 * Delete all handlers and close connections.
	 * Will cause every call of get* to recreate an handler.
	 * Use this function if you suspect your DB handler might have timed out.
	 */
	static public function deleteHandlers()
	{
		self::$handlers = array();
	}

}
