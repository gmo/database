<?php
namespace GMO\Database;

abstract class PdoDbConnection extends DbConnection {

	abstract function getDsn();
} 