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

	/**
	 * Recasts the object as a child object
	 * @param DbConnection $conn
	 * @return DbConnection
	 */
	public static function fromSelf(DbConnection $conn) {
		$cls = get_called_class();
		return new $cls($conn->getUser(),
						$conn->getPassword(),
						$conn->getHost(),
						$conn->getSchema());
	}

	private $user;
	private $password;
	private $host;
	private $schema;
} 