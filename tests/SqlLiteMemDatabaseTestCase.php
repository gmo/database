<?php

require_once __DIR__ . "/tester_autoload.php";

class SqlLiteMemDatabaseTestCase extends \GMO\Database\AbstractDatabaseTestCase {

	protected function  getPdoDbConnection() {
		return new \GMO\Database\SqliteMemPdoDbConnection();
	}
	protected function preSetup() {
		parent::preSetup();
		$this->createDbTables();
	}
	protected function createDbTables() {
		$this->getConnection()->
			getConnection()->
			query( "CREATE TABLE guestbook (id INTEGER PRIMARY KEY, content STRING, user STRING, created STRING)");
	}

	/**
	 * Returns the test dataset.
	 * @return \PHPUnit\DbUnit\DataSet\AbstractDataSet
	 */
	protected function getDataSet() {
		return new \PHPUnit\DbUnit\DataSet\YamlDataSet(
			__DIR__ . "/data/guestbook.yml"
		);
	}

	public function testSqliteMemPdoDbConnection() {
		$this->assertEquals(2, $this->getConnection()->getRowCount('guestbook'), "Pre-Condition");
	}

}
