<?php
namespace GMO\Database\Connection;

abstract class PdoDbConnection extends DbConnection {

	abstract function getDsn();
} 
