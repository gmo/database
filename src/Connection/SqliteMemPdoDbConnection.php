<?php
namespace GMO\Database\Connection;

class SqliteMemPdoDbConnection extends PdoDbConnection {

	function getDsn() {
		return "sqlite::memory:";
	}

	public function __construct() {
		parent::__construct(":memory:", null, null, null);
	}

	public static function fromSelf(DbConnection $conn) {
		return static::__construct();
	}
}
