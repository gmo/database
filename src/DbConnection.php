<?php
namespace Gmo\Database;

class DbConnection {

	public function getUser() {
		return $this->user;
	}
	public function getPassword() {
		return $this->password;
	}
	public function getHost() {
		return $this->host;
	}
	public function getSchema() {
		return $this->schema;
	}

	public function getPort() {
		return $this->port;
	}

	public function getSlave() {
		return $this->slave;
	}

	public function setSlave(DbConnection $connection = null) {
		$this->slave = $connection;
	}

	public function __construct($user, $password, $host, $schema, $port = 3306) {

		$this->user = $user;
		$this->password = $password;
		$this->host = $host;
		$this->schema = $schema;
		$this->port = $port;
	}

	/**
	 * Recasts the object as a child object
	 * @param DbConnection $conn
	 * @return DbConnection
	 */
	public static function fromSelf(DbConnection $conn) {
		$cls = get_called_class();
		$cls = new $cls($conn->getUser(),
		                $conn->getPassword(),
		                $conn->getHost(),
		                $conn->getSchema());
		$cls->setSlave($conn->getSlave());
		return $cls;
	}
	private $user;
	private $password;
	private $host;
	private $schema;
	private $port;
	private $slave;
} 
