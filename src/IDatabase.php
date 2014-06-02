<?php
namespace GMO\Database;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Database interface designed to provide basic query functions.
 * @package GMO\Database
 */
interface IDatabase extends LoggerAwareInterface {
	/**
	 * Sets a logger instance on the object
	 * @param LoggerInterface $logger
	 * @return null
	 */
	public function setLogger(LoggerInterface $logger);

	/**
	 * Force next query to use master database connection
	 * @return $this
	 */
	public function useMaster();

	/**
	 * Next query will be ran without locking table
	 * @return $this
	 */
	public function withNoLock();

	/**
	 * Wraps a function in a transaction.
	 * The current object ({@see IDatabase}) is passed to the function.
	 *
	 * If an {@see \Exception exception} is thrown the transaction is rolled back.
	 *
	 * $result = $this->withTransaction(function(IDatabase $db) {
	 *      return $db->singleValue("select id from foo");
	 * });
	 *
	 * @param $callback callable with IDatabase argument passed to it
	 * @return mixed
	 */
	public function withTransaction($callback);

	/**
	 * Returns a single value from the first column
	 * of the first row of results from query.
	 * @param string $query
	 * @param mixed  $params variable number
	 * @throws DatabaseException if query fails
	 * @return mixed
	 */
	public function singleValue($query, $params = null);

	/**
	 * Returns a key/value array of the first row
	 * of results from query.
	 * @param string $query
	 * @param mixed  $params variable number
	 * @throws DatabaseException if query fails
	 * @return array
	 */
	public function singleRow($query, $params = null);

	/**
	 * Returns a list of a single column.
	 * @example keyValueArray("SELECT value FROM Foo");
	 * @param string $query
	 * @param mixed  $params variable number
	 * @return array array( value1, value2 )
	 */
	public function singleColumn($query, $params = null);

	/**
	 * Returns a key value array.
	 * This will deduplicate the data based on the key.
	 * If the query only has one column the keys are set to the values.
	 * If the query has more than two columns the entire row is used for the values.
	 * @example keyValueArray("SELECT id, value FROM Foo");
	 * @param string $query
	 * @param mixed  $params variable number
	 * @return array array( id1 => value1, id2 => value2 )
	 */
	public function keyValueArray($query, $params = null);

	/**
	 * Inserts a row and returns the id
	 * @param string $query
	 * @param mixed  $params variable number
	 * @throws DatabaseException if query fails
	 * @return array
	 */
	public function insertAndReturnId($query, $params = null);

	/**
	 * Returns the last insert id on the master db connection
	 * @return int
	 */
	public function getInsertId();

	/**
	 * Returns a list of key/value arrays of results
	 * from query.
	 * @param string $query
	 * @param mixed  $params variable number
	 * @throws DatabaseException if query fails
	 * @return array
	 */
	public function execute($query, $params = null);

	/**
	 * Gets the number of affected rows from last query
	 * @return int number of affected rows
	 */
	public function getAffectedRows();
}
