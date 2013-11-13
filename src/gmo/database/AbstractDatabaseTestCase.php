<?php
namespace GMO\Database;

abstract class AbstractDatabaseTestCase extends \PHPUnit_Extensions_Database_TestCase {

	/**
	 * @return PdoDbConnection
	 */
	abstract function getPdoConnection();

	/**
	 * Returns the test database connection.
	 * @return \PHPUnit_Extensions_Database_DB_IDatabaseConnection
	 */
	protected function getConnection() {

		if ( $this->conn === null ) {

			$pdoConn = $this->getPdoConnection();

			if ( self::$pdo == null || $pdoConn !== self::$pdoConn) {
				self::$pdo = new \PDO( $pdoConn->getDsn(), $pdoConn->getUser(), $pdoConn->getPassword() );
				self::$pdoConn = $pdoConn;
			}

			$this->conn = $this->createDefaultDBConnection( self::$pdo, $pdoConn->getSchema() );
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
	static private $pdoConn;
	# Only instantiate PHPUnit_Extensions_Database_DB_IDatabaseConnection once per test
	private $conn = null;
}