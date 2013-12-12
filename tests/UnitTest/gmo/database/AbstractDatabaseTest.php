<?php
namespace UnitTest\Database;

use GMO\Database\AbstractDatabase;
use Psr\Log\LoggerInterface;

require_once __DIR__ . "/../../../tester_autoload.php";

class AbstractDatabaseTest extends \PHPUnit_Framework_TestCase {
	const SLAVE_CLASS_MOCK = '\UnitTest\Database\SlaveDatabaseMock';
	const MASTER_CLASS_MOCK = '\UnitTest\Database\MasterDatabaseMock';

	public function test_chooseDbByQuery_with_select_and_with_a_slave_db() {
		$db = new TestableAbstractDatabase(new SlaveDatabaseMock());
		$this->assertInstanceOf(self::SLAVE_CLASS_MOCK, $db->chooseDbByQuery('SELECT * FROM foo'));
	}
	
	public function test_chooseDbByQuery_with_select_into_outfile_and_with_a_slave_db() {
		$db = new TestableAbstractDatabase(new SlaveDatabaseMock());
		$this->assertInstanceOf(self::MASTER_CLASS_MOCK, $db->chooseDbByQuery('SELECT * FROM foo INTO OUTFILE "/tmp/bar.txt"'));
	}
	
	public function test_chooseDbByQuery_with_select_into_dumpfile_and_with_a_slave_db() {
		$db = new TestableAbstractDatabase(new SlaveDatabaseMock());
		$this->assertInstanceOf(self::MASTER_CLASS_MOCK, $db->chooseDbByQuery('SELECT * FROM foo INTO DUMPFILE "/tmp/bar.txt"'));
	}
	
	public function test_chooseDbByQuery_with_insert_and_with_a_slave_db() {
		$db = new TestableAbstractDatabase(new SlaveDatabaseMock());
		$this->assertInstanceOf(self::MASTER_CLASS_MOCK, $db->chooseDbByQuery('INSERT INTO foo (a) VALUES (1)'));
	}
	
	public function test_chooseDbByQuery_with_insert_into_select_from_and_with_a_slave_db() {
		$db = new TestableAbstractDatabase(new SlaveDatabaseMock());
		$this->assertInstanceOf(self::MASTER_CLASS_MOCK, $db->chooseDbByQuery('INSERT INTO foo (a) SELECT (b) FROM bar WHERE id=1'));
	}
	
	public function test_chooseDbByQuery_with_update_and_with_a_slave_db() {
		$db = new TestableAbstractDatabase(new SlaveDatabaseMock());
		$this->assertInstanceOf(self::MASTER_CLASS_MOCK, $db->chooseDbByQuery('UPDATE foo SET a = 1 WHERE id=1'));
	}
	
	public function test_chooseDbByQuery_with_delete_and_with_a_slave_db() {
		$db = new TestableAbstractDatabase(new SlaveDatabaseMock());
		$this->assertInstanceOf(self::MASTER_CLASS_MOCK, $db->chooseDbByQuery('DELETE FROM foo WHERE id=1'));
	}
	
	public function test_chooseDbByQuery_with_select_and_no_slave_db() {
		$db = new TestableAbstractDatabase();
		$this->assertInstanceOf(self::MASTER_CLASS_MOCK, $db->chooseDbByQuery('SELECT * FROM foo'));
	}
	
	public function test_chooseDbByQuery_with_select_into_outfile_and_no_slave_db() {
		$db = new TestableAbstractDatabase();
		$this->assertInstanceOf(self::MASTER_CLASS_MOCK, $db->chooseDbByQuery('SELECT * FROM foo INTO OUTFILE "/tmp/bar.txt"'));
	}
	
	public function test_chooseDbByQuery_with_select_into_dumpfile_and_no_slave_db() {
		$db = new TestableAbstractDatabase();
		$this->assertInstanceOf(self::MASTER_CLASS_MOCK, $db->chooseDbByQuery('SELECT * FROM foo INTO DUMPFILE "/tmp/bar.txt"'));
	}
	
	public function test_chooseDbByQuery_with_insert_and_no_slave_db() {
		$db = new TestableAbstractDatabase();
		$this->assertInstanceOf(self::MASTER_CLASS_MOCK, $db->chooseDbByQuery('INSERT INTO foo (a) VALUES (1)'));
	}
	
	public function test_chooseDbByQuery_with_insert_into_select_from_and_no_slave_db() {
		$db = new TestableAbstractDatabase();
		$this->assertInstanceOf(self::MASTER_CLASS_MOCK, $db->chooseDbByQuery('INSERT INTO foo (a) SELECT (b) FROM bar WHERE id=1'));
		
	}
	
	public function test_chooseDbByQuery_with_update_and_no_slave_db() {
		$db = new TestableAbstractDatabase();
		$this->assertInstanceOf(self::MASTER_CLASS_MOCK, $db->chooseDbByQuery('UPDATE foo SET a = 1 WHERE id=1'));
	}
	
	public function test_chooseDbByQuery_with_delete_and_no_slave_db() {
		$db = new TestableAbstractDatabase();
		$this->assertInstanceOf(self::MASTER_CLASS_MOCK, $db->chooseDbByQuery('DELETE FROM foo WHERE id=1'));
	}

}


class TestableAbstractDatabase extends AbstractDatabase {
	function __construct($slave = null) {
		$this->setDbMaster(new MasterDatabaseMock());
		$this->setDbSlave($slave);
	}

	public function chooseDbByQuery($query) {
		return parent::chooseDbByQuery($query);
	}

	public function setLog(LoggerInterface $logger) {}
}

class DatabaseMock {}
class MasterDatabaseMock extends DatabaseMock {}
class SlaveDatabaseMock extends DatabaseMock {}
