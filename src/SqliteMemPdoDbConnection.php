<?php
namespace Gmo\Database;

class SqliteMemPdoDbConnection extends PdoDbConnection {

	function getDsn() {
		return "sqlite::memory:";
	}

	public function __construct() {
		parent::__construct(null, null, null, ":memory:");
	}

	public static function fromSelf(DbConnection $conn) {
		return static::__construct();
	}
}
