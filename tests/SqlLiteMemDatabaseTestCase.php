<?php

use GMO\Database\Connection\SqliteMemPdoDbConnection;

require_once __DIR__ . "/tester_autoload.php";

class SqlLiteMemDatabaseTestCase extends \GMO\Database\AbstractDatabaseTestCase {

	protected function  getPdoDbConnection() {
		return new SqliteMemPdoDbConnection();
	}

	protected function preSetup() {
		parent::preSetup();
		$this->createDbTables();
	}

	protected function createDbTables() {
		$this->getConnection()->getConnection()->query(
			"CREATE TABLE guestbook (id INTEGER PRIMARY KEY, content STRING, user STRING, created STRING)");
	}

	/**
	 * Returns the test dataset.
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	protected function getDataSet() {
		return new PHPUnit_Extensions_Database_DataSet_YamlDataSet(__DIR__ . "/data/guestbook.yml");
	}

	public function testSqliteMemPdoDbConnection() {
		$this->assertEquals(2, $this->getConnection()->getRowCount('guestbook'), "Pre-Condition");
	}

}
