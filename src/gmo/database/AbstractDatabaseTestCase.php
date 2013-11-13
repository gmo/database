<?php
namespace GMO\Database;

abstract class AbstractDatabaseTestCase extends \PHPUnit_Extensions_Database_TestCase {

	/** @return PdoDbConnection */
	protected function getPdoDbConnection() {}

	protected function preSetup() {
		if(self::$pdoConn instanceof MySqlPdoDbConnection) {
			$this->conn->getConnection()->query( "SET FOREIGN_KEY_CHECKS=0" );
		}
	}
	protected function postSetup() {
		if(self::$pdoConn instanceof MySqlPdoDbConnection) {
			$this->conn->getConnection()->query( "SET FOREIGN_KEY_CHECKS=1" );
		}
	}

	/** @deprecated Remove in v2.0.0 use getPdoDbConnection */
	protected function getUsername() { return ""; }
	/** @deprecated Remove in v2.0.0 use getPdoDbConnection */
	protected function getPassword() { return ""; }
	/** @deprecated Remove in v2.0.0 use getPdoDbConnection */
	protected function getHost() { return ""; }
	/** @deprecated Remove in v2.0.0 use getPdoDbConnection */
	protected function getDatabase() { return ""; }

	/**
	 * Returns the test database connection.
	 * @return \PHPUnit_Extensions_Database_DB_IDatabaseConnection
	 */
	protected function getConnection() {

		if ( $this->conn === null ) {

			$pdoConn = $this->getPdoDbConnection();
			if ($pdoConn == null) {
				$pdoConn = new MySqlPdoDbConnection(
					$this->getUsername(),
					$this->getPassword(),
					$this->getHost(),
					$this->getDatabase()
				);
			}

			if ( self::$pdo == null || $pdoConn !== self::$pdoConn) {
				self::$pdo = new \PDO( $pdoConn->getDsn(), $pdoConn->getUser(), $pdoConn->getPassword() );
				self::$pdoConn = $pdoConn;
			}

			$this->conn = $this->createDefaultDBConnection( self::$pdo, $pdoConn->getSchema() );
		}

		return $this->conn;
	}

	protected function setUp() {
		$this->getConnection();
		$this->preSetup();
		parent::setUp();
		$this->postSetup();
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
	/** @var \PHPUnit_Extensions_Database_DB_IDatabaseConnection */
	private $conn = null;
}