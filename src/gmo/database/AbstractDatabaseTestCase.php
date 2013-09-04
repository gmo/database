<?php
namespace gmo\database;

abstract class AbstractDatabaseTestCase extends \PHPUnit_Extensions_Database_TestCase {

	# Only instantiate connection once for test clean-up/fixture load
	static private $pdo = null;
	# Only instantiate PHPUnit_Extensions_Database_DB_IDatabaseConnection once per test
	private $conn = null;

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
			if ( self::$pdo == null ) {
				self::$pdo =
					new \PDO("mysql:host=" . $this->getHost() . ";dbname=" . $this->getDatabase(),
							$this->getUsername(),
							$this->getPassword());
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
}