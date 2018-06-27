<?php
namespace Gmo\Database;

abstract class PdoDbConnection extends DbConnection {

	abstract function getDsn();
} 
