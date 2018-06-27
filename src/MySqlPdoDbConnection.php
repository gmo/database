<?php
namespace Gmo\Database;

class MySqlPdoDbConnection extends PdoDbConnection {

	function getDsn() {
		return "mysql:dbname=" . $this->getSchema() . ";host=" . $this->getHost();
	}
}
