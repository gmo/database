<?php
namespace GMO\Database;

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

	public function __construct($user, $password, $host, $schema) {

		$this->user = $user;
		$this->password = $password;
		$this->host = $host;
		$this->schema = $schema;
	}

	private $user;
	private $password;
	private $host;
	private $schema;
} 