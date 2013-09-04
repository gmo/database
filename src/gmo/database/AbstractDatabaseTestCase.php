<?php
namespace gmo\database;

abstract class AbstractDatabaseTestCase extends \PHPUnit_Extensions_Database_TestCase {

	protected $iniPrefix = "";

	# Only instantiate connection once for test clean-up/fixture load
	static private $pdo = null;
	# Only instantiate PHPUnit_Extensions_Database_DB_IDatabaseConnection once per test
	private $conn = null;


	/**
	 * Returns the test database connection.
	 * @return \PHPUnit_Extensions_Database_DB_IDatabaseConnection
	 */
	protected function getConnection() {
		if ( $this->conn === null ) {
			if ( self::$pdo == null ) {
				self::$pdo =
					new \PDO("mysql:host=" .
					         Config::getDatabaseHost() .
					         ";dbname=" .
					         Config::getDatabaseSchema( $this->iniPrefix ), Config::getDatabaseUsername(
					         ), Config::getDatabasePassword());
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