<?php
namespace GMO\Database;

abstract class AbstractDatabaseTestCase extends \PHPUnit_Extensions_Database_TestCase {

	/**
	 * Should return the database host
	 * @return string
	 */
	protected abstract function getHost();

	/**
	 * Should return the database username
	 * @return string
	 */
	protected abstract function getUsername();

	/**
	 * Should return the database password
	 * @return string
	 */
	protected abstract function getPassword();

	/**
	 * Should return the database schema
	 * @return string
	 */
	protected abstract function getDatabase();

	/**
	 * Returns the test database connection.
	 * @return \PHPUnit_Extensions_Database_DB_IDatabaseConnection
	 */
	protected function getConnection() {
		if ( $this->conn === null ) {
			$newConnStr = "mysql:host=" . $this->getHost() . ";dbname=" . $this->getDatabase();
			if ( self::$pdo == null || $newConnStr !== self::$connStr ) {
				self::$pdo = new \PDO( $newConnStr, $this->getUsername(), $this->getPassword() );
				self::$connStr = $newConnStr;
			}
			$this->conn = $this->createDefaultDBConnection( self::$pdo, "mysql" );
		}

		return $this->conn;
	}

	protected function setUp() {
		$conn = $this->getConnection();
		$conn->getConnection()->query( "SET FOREIGN_KEY_CHECKS=0" );
		parent::setUp();
		$conn->getConnection()->query( "SET FOREIGN_KEY_CHECKS=1" );
	}

	protected function runSelectQuery( $query ) {
		$conn = $this->getConnection();

		$dataSet = array();
		foreach ( $conn->getConnection()->query( $query ) as $row ) {
			$dataSet[] = $row;
		}

		return $dataSet;
	}

	# Only instantiate connection once for test clean-up/fixture load
	static private $pdo = null;
	static private $connStr;
	# Only instantiate PHPUnit_Extensions_Database_DB_IDatabaseConnection once per test
	private $conn = null;
}