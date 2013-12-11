<?php
namespace UnitTest\Database;

require_once __DIR__ . "/../../../tester_autoload.php";

class AbstractDatabaseTest extends \PHPUnit_Framework_TestCase {

	public function test_chooseDbByQuery_with_select_and_with_a_slave_db() {
		$db = new TestableAbstractDatabase();
		$this->assertInstanceOf('\UnitTest\Database\SlaveDatabaseMock', $db->chooseDbByQuery('SELECT * FROM foo'));
	}
	
	public function test_chooseDbByQuery_with_select_into_outfile_and_with_a_slave_db() {
		$db = new TestableAbstractDatabase();
		$this->assertInstanceOf('\UnitTest\Database\MasterDatabaseMock', $db->chooseDbByQuery('SELECT * FROM foo INTO OUTFILE "/tmp/bar.txt"'));
	
	}
	
	public function test_chooseDbByQuery_with_select_into_dumpfile_and_with_a_slave_db() {
		$db = new TestableAbstractDatabase();
		$this->assertInstanceOf('\UnitTest\Database\MasterDatabaseMock', $db->chooseDbByQuery('SELECT * FROM foo INTO DUMPFILE "/tmp/bar.txt"'));
	}
	
	public function test_chooseDbByQuery_with_insert_and_with_a_slave_db() {
		$db = new TestableAbstractDatabase();
		$this->assertInstanceOf('\UnitTest\Database\MasterDatabaseMock', $db->chooseDbByQuery('INSERT INTO foo (a) VALUES (1)'));
	}
	
	public function test_chooseDbByQuery_with_insert_into_select_from_and_with_a_slave_db() {
		$db = new TestableAbstractDatabase();
		$this->assertInstanceOf('\UnitTest\Database\MasterDatabaseMock', $db->chooseDbByQuery('INSERT INTO foo (a) SELECT (b) FROM bar WHERE id=1'));
	
	}
	
	public function test_chooseDbByQuery_with_update_and_with_a_slave_db() {
		$db = new TestableAbstractDatabase();
		$this->assertInstanceOf('\UnitTest\Database\MasterDatabaseMock', $db->chooseDbByQuery('UPDATE foo SET a = 1 WHERE id=1'));
	}
	
	public function test_chooseDbByQuery_with_delete_and_with_a_slave_db() {
		$db = new TestableAbstractDatabase();
		$this->assertInstanceOf('\UnitTest\Database\MasterDatabaseMock', $db->chooseDbByQuery('DELETE FROM foo WHERE id=1'));
	}
	
	public function test_chooseDbByQuery_with_select_and_no_slave_db() {
		$db = new TestableAbstractDatabase();
		$db->noSlaveDatabase();
		$this->assertInstanceOf('\UnitTest\Database\MasterDatabaseMock', $db->chooseDbByQuery('SELECT * FROM foo'));
	}
	
	public function test_chooseDbByQuery_with_select_into_outfile_and_no_slave_db() {
		$db = new TestableAbstractDatabase();
		$db->noSlaveDatabase();
		$this->assertInstanceOf('\UnitTest\Database\MasterDatabaseMock', $db->chooseDbByQuery('SELECT * FROM foo INTO OUTFILE "/tmp/bar.txt"'));
	}
	
	public function test_chooseDbByQuery_with_select_into_dumpfile_and_no_slave_db() {
		$db = new TestableAbstractDatabase();
		$db->noSlaveDatabase();
		$this->assertInstanceOf('\UnitTest\Database\MasterDatabaseMock', $db->chooseDbByQuery('SELECT * FROM foo INTO DUMPFILE "/tmp/bar.txt"'));
	}
	
	public function test_chooseDbByQuery_with_insert_and_no_slave_db() {
		$db = new TestableAbstractDatabase();
		$db->noSlaveDatabase();
		$this->assertInstanceOf('\UnitTest\Database\MasterDatabaseMock', $db->chooseDbByQuery('INSERT INTO foo (a) VALUES (1)'));
	}
	
	public function test_chooseDbByQuery_with_insert_into_select_from_and_no_slave_db() {
		$db = new TestableAbstractDatabase();
		$db->noSlaveDatabase();
		$this->assertInstanceOf('\UnitTest\Database\MasterDatabaseMock', $db->chooseDbByQuery('INSERT INTO foo (a) SELECT (b) FROM bar WHERE id=1'));
		
	}
	
	public function test_chooseDbByQuery_with_update_and_no_slave_db() {
		$db = new TestableAbstractDatabase();
		$db->noSlaveDatabase();
		$this->assertInstanceOf('\UnitTest\Database\MasterDatabaseMock', $db->chooseDbByQuery('UPDATE foo SET a = 1 WHERE id=1'));
	}
	
	public function test_chooseDbByQuery_with_delete_and_no_slave_db() {
		$db = new TestableAbstractDatabase();
		$db->noSlaveDatabase();
		$this->assertInstanceOf('\UnitTest\Database\MasterDatabaseMock', $db->chooseDbByQuery('DELETE FROM foo WHERE id=1'));
	}
	
	public function test_setupSlaveDbAttributes_with_complete_connection() {
		$connection = new \GMO\Database\DbConnection('user', 'password', '127.0.0.1', 'schema');
		$db = new TestableAbstractDatabase();
		$db->setupSlaveDbAttributes($connection);
		$this->assertEquals( 'user', $db->getSlaveUsername() );
		$this->assertEquals( 'password', $db->getSlavePassword() );
		$this->assertEquals( '127.0.0.1', $db->getSlaveHost() );
		$this->assertEquals( 'schema', $db->getSlaveDatabase() );
	}
	
	public function test_setupSlaveDbAttributes_with_null_in_schema() {
		$connection = new \GMO\Database\DbConnection('user', 'password', '127.0.0.1', null);
		$db = new TestableAbstractDatabase();
		$db->setupSlaveDbAttributes($connection);
		$this->assertEquals( 'user', $db->getSlaveUsername() );
		$this->assertEquals( 'password', $db->getSlavePassword() );
		$this->assertEquals( '127.0.0.1', $db->getSlaveHost() );
		$this->assertEquals( null, $db->getSlaveDatabase() );
	}

	public function test_setupSlaveDbAttributes_with_null_host_in_connection() {
		$connection = new \GMO\Database\DbConnection('user', 'password', null, 'schema');
		$db = new TestableAbstractDatabase();
		$db->setupSlaveDbAttributes($connection);
		$this->assertEquals( 'user', $db->getSlaveUsername() );
		$this->assertEquals( 'password', $db->getSlavePassword() );
		$this->assertEquals( null, $db->getSlaveHost() );
		$this->assertEquals( 'schema', $db->getSlaveDatabase() );
	}
	
	public function test_setupSlaveDbAttributes_with_null_connection() {
		$db = new TestableAbstractDatabase();
		$db->setupSlaveDbAttributes(null);
		$this->assertEquals( null, $db->getSlaveUsername() );
		$this->assertEquals( null, $db->getSlavePassword() );
		$this->assertEquals( null, $db->getSlaveHost() );
		$this->assertEquals( null, $db->getSlaveDatabase() );
	}
	
}


class TestableAbstractDatabase extends \GMO\Database\AbstractDatabase {
	function __construct() {
		$this->setDbMaster(new MasterDatabaseMock());
		$this->setDbSlave(new SlaveDatabaseMock());
	}
	
	public function noSlaveDatabase() {
		$this->setDbSlave(null);
	}
	
	public function chooseDbByQuery($query) {
		return parent::chooseDbByQuery($query);
	}
	
	public function setupSlaveDbAttributes($slaveConnection) {
		return parent::setupSlaveDbAttributes($slaveConnection);
	}
	
	public function getHost() {
		return parent::getHost();
	}
	
	public function getUsername() {
		return parent::getUsername();
	}
	
	public function getPassword() {
		return parent::getPassword();
	}
	
	public function getDatabase() {
		return parent::getDatabase();
	}
	
	public function getSlaveHost() {
		return parent::getSlaveHost();
	}
	
	public function getSlaveUsername() {
		return parent::getSlaveUsername();
	}
	
	public function getSlavePassword() {
		return parent::getSlavePassword();
	}
	
	public function getSlaveDatabase() {
		return parent::getSlaveDatabase();
	}
}

class DatabaseMock {}
class MasterDatabaseMock extends DatabaseMock {}
class SlaveDatabaseMock extends DatabaseMock {}
