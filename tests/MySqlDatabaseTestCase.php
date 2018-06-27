<?php

require_once __DIR__ . "/tester_autoload.php";

class MySqlDatabaseTestCase extends \GMO\Database\AbstractDatabaseTestCase {

	protected function  getPdoDbConnection() {
		return new \GMO\Database\MySqlPdoDbConnection("unittestuser", "Password1", "127.0.0.1", "db-lib-test");
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
		$this->assertEquals(2, $this->getConnection()->getRowCount("guestbook"), "Pre-Condition");
	}

}

/** @deprecated Remove in v2.0.0 */
class MySqlDatabaseTestCase_Deprecated_Test extends \GMO\Database\AbstractDatabaseTestCase {

	/** @deprecated Remove in v2.0.0 use getPdoDbConnection */
	protected function getUsername() { return "unittestuser"; }
	/** @deprecated Remove in v2.0.0 use getPdoDbConnection */
	protected function getPassword() { return "Password1"; }
	/** @deprecated Remove in v2.0.0 use getPdoDbConnection */
	protected function getHost() { return "127.0.0.1"; }
	/** @deprecated Remove in v2.0.0 use getPdoDbConnection */
	protected function getDatabase() { return "db-lib-test"; }

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
		$this->assertEquals(2, $this->getConnection()->getRowCount("guestbook"), "Pre-Condition");
	}

}
