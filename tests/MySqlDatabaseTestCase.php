<?php

use GMO\Database\Connection\MySqlPdoDbConnection;

require_once __DIR__ . "/tester_autoload.php";

class MySqlDatabaseTestCase extends \GMO\Database\AbstractDatabaseTestCase {

	protected function  getPdoDbConnection() {
		return new MySqlPdoDbConnection("db-lib-test", "unittestuser", "Password1", "127.0.0.1");
	}

	/**
	 * Returns the test dataset.
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	protected function getDataSet() {
		return new PHPUnit_Extensions_Database_DataSet_YamlDataSet(
			__DIR__ . "/data/guestbook.yml"
		);
	}

	public function testSqliteMemPdoDbConnection() {
		$this->assertEquals(2, $this->getConnection()->getRowCount("guestbook"), "Pre-Condition");
	}

}

