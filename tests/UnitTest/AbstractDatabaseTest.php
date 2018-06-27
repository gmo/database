<?php
namespace UnitTest;

use Gmo\Database\AbstractDatabase;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

require_once __DIR__ . "/../tester_autoload.php";

class AbstractDatabaseTest extends TestCase {

	public function testExpandQueryParamsWithArray() {
		$query = "SELECT * from FOO WHERE something IN (??) AND color = ?";
		$params = array(
			array(1, 2, 3),
		    "blue"
		);

		list($query, $params) = $this->invokeMethod("expandQueryParams", array($query, $params));

		$this->assertSame("SELECT * from FOO WHERE something IN (?, ?, ?) AND color = ?", $query);
		$this->assertSame(array(1, 2, 3, "blue"), $params);
	}

	protected function invokeMethod($method, $params) {
		$db = new \ReflectionClass("\\UnitTest\\TestableAbstractDatabase");
		$method = $db->getMethod($method);
		$method->setAccessible(true);
		return $method->invokeArgs($db->newInstance(), $params);
	}
}

//region Master Slave Test
class MasterSlaveTest extends TestCase {
	const SLAVE_CLASS_MOCK = '\UnitTest\Database\SlaveDatabaseMock';
	const MASTER_CLASS_MOCK = '\UnitTest\Database\MasterDatabaseMock';

	public function test_chooseDbByQuery_with_select_and_with_a_slave_db() {
		$this->assert_slave($this->dbWithSlave, 'SELECT * FROM foo');
	}

	public function test_chooseDbByQuery_with_select_and_with_a_slave_db_and_forcing_master() {
		$this->assert_master($this->dbWithSlave, 'M_SELECT * FROM foo');
	}
	
	public function test_chooseDbByQuery_with_select_into_outfile_and_with_a_slave_db() {
		$this->assert_master($this->dbWithSlave, 'SELECT * FROM foo INTO OUTFILE "/tmp/bar.txt"');
	}
	
	public function test_chooseDbByQuery_with_select_into_dumpfile_and_with_a_slave_db() {
		$this->assert_master($this->dbWithSlave, 'SELECT * FROM foo INTO DUMPFILE "/tmp/bar.txt"');
	}
	
	public function test_chooseDbByQuery_with_insert_and_with_a_slave_db() {
		$this->assert_master($this->dbWithSlave, 'INSERT INTO foo (a) VALUES (1)');
	}
	
	public function test_chooseDbByQuery_with_insert_into_select_from_and_with_a_slave_db() {
		$this->assert_master($this->dbWithSlave, 'INSERT INTO foo (a) SELECT (b) FROM bar WHERE id=1');
	}
	
	public function test_chooseDbByQuery_with_update_and_with_a_slave_db() {
		$this->assert_master($this->dbWithSlave, 'UPDATE foo SET a = 1 WHERE id=1');
	}
	
	public function test_chooseDbByQuery_with_delete_and_with_a_slave_db() {
		$this->assert_master($this->dbWithSlave, 'DELETE FROM foo WHERE id=1');
	}
	
	public function test_chooseDbByQuery_with_select_and_no_slave_db() {
		$this->assert_master($this->db, 'SELECT * FROM foo');
	}
	
	public function test_chooseDbByQuery_with_select_into_outfile_and_no_slave_db() {
		$this->assert_master($this->db, 'SELECT * FROM foo INTO OUTFILE "/tmp/bar.txt"');
	}
	
	public function test_chooseDbByQuery_with_select_into_dumpfile_and_no_slave_db() {
		$this->assert_master($this->db, 'SELECT * FROM foo INTO DUMPFILE "/tmp/bar.txt"');
	}
	
	public function test_chooseDbByQuery_with_insert_and_no_slave_db() {
		$this->assert_master($this->db, 'INSERT INTO foo (a) VALUES (1)');
	}
	
	public function test_chooseDbByQuery_with_insert_into_select_from_and_no_slave_db() {
		$this->assert_master($this->db, 'INSERT INTO foo (a) SELECT (b) FROM bar WHERE id=1');
	}
	
	public function test_chooseDbByQuery_with_update_and_no_slave_db() {
		$this->assert_master($this->db, 'UPDATE foo SET a = 1 WHERE id=1');
	}
	
	public function test_chooseDbByQuery_with_delete_and_no_slave_db() {
		$this->assert_master($this->db, 'DELETE FROM foo WHERE id=1');
	}

	protected function assert_master($db, $query) {
		$this->assertInstanceOf(self::MASTER_CLASS_MOCK, $db->chooseDbByQuery($query));
	}
	protected function assert_slave($db, $query) {
		$this->assertInstanceOf(self::SLAVE_CLASS_MOCK, $db->chooseDbByQuery($query));
	}

	protected function setUp() {
		$this->db = new TestableAbstractDatabase();
		$this->dbWithSlave = new TestableAbstractDatabase(new SlaveDatabaseMock());
	}

	/** @var TestableAbstractDatabase */
	private $db;
	/** @var TestableAbstractDatabase */
	private $dbWithSlave;
}
//endregion

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

class DatabaseMock {
	function close() {}
}
class MasterDatabaseMock extends DatabaseMock {}
class SlaveDatabaseMock extends DatabaseMock {}
