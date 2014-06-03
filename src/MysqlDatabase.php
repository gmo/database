<?php
namespace GMO\Database;

use GMO\Common\String;
use GMO\Database\Connection\DbConnection;
use GMO\Database\Exception\DatabaseException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @package GMO\Database
 */
class MysqlDatabase extends AbstractDatabase {

	#region Variables
	/** @var \mysqli */
	private $dbMaster;
	/** @var \mysqli */
	private $dbSlave;

	/** @var DbConnection */
	private $dbMasterConnection;
	/** @var DbConnection|null */
	private $dbSlaveConnection;

	/** @var int number of affected rows from last query */
	private $affectedRows;

	#endregion

	/**
	 * Runs sql scripts to setup database tables
	 * @param string $path directory without ending slash
	 */
	public function runScriptsFromDir($path) {
		$path = realpath($path);

		$this->log->info("========================");
		$this->log->info("Running scripts in:   " . $path . "/*.sql");
		$files = glob($path . "/*.sql");
		$this->log->info("Number scripts found: " . count($files));
		$this->log->info("========================");

		foreach ($files as $file) {
			$data = file_get_contents($file);

			// Remove C style and inline comments
			$comment_patterns = array(
			'/\/\*.*(\n)*.*(\*\/)?/', //C comments
			'/\s*--.*\n/', //inline comments start with --
			'/\s*#.*\n/', //inline comments start with #
			);
			$data = preg_replace($comment_patterns, "\n", $data);

			//Retrieve sql statements
			$stmts = explode(";", $data);
			$stmts = preg_replace('#[^\S\n]#', " ", $stmts);
			$stmts = preg_replace("[\n]", "\n\t", $stmts);

			foreach ($stmts as $query) {
				if (trim($query) == "") {
					continue;
				}
				$this->log->info("Executing query:\n\t" . $query);
				$this->reConnect();
				$db = $this->chooseDbByQuery($query);
				$db->query($query);
				if ($db->errno == 0) {
					$this->log->info("Execution: SUCCESS");
				} elseif ($db->errno == 1060) // Duplicate column
				{
					$this->log->warning("Execution: " . $db->error);
				} else {
					$this->log->error("Execution: " . $db->error);
				}
				$this->log->info("============");
			}
		}
	}

	/** @inheritdoc */
	public function withTransaction(callable $callback) {
		$this->dbMaster->autocommit(false);
		$oldSlave = $this->dbSlave;
		$this->dbSlave = $this->dbMaster;
		$db = $this;
		$result = null;
		try {
			$result = $callback($db);
			$this->dbMaster->commit();
			$this->dbMaster->autocommit(true);
			$this->dbSlave = $oldSlave;
		} catch (\Exception $e) {
			$this->dbMaster->rollback();
			$this->dbMaster->autocommit(true);
			$this->dbSlave = $oldSlave;
			throw $e;
		}

		return $result;
	}

	/** @inheritdoc */
	public function getInsertId() {
		return $this->dbMaster->insert_id;
	}

	/** @inheritdoc */
	public function execute($query, $params = null) {
		# Get query and params
		$params = func_get_args();
		$query = array_shift($params);

		# Update query and params with params that have arrays.
		list($query, $params) = $this->expandQueryParams($query, $params);

		$this->reConnect();

		# Create statement
		$db = $this->chooseDbByQuery($query);
		$this->forceMaster = false;
		$this->openNoLock($db);

		$connection = $db->thread_id === $this->dbMaster->thread_id ? "master" : "slave";
		$this->log->debug($query, array(
			'params' => $params,
			'connection' => $connection,
			'usingNoLock' => $this->usingNoLock));

		$stmt = $db->prepare( $query );
		if (!$stmt) {
			$this->closeNoLock($db);
			$this->throwDbException("Error preparing statement", $query, $params, $db);
		}

		$stmt = $this->bindParamsToStmt($stmt, $params);

		# Execute query
		if (!$stmt->execute()) {
			$this->closeNoLock($db);
			$this->throwDbException("Error executing statement", $query, $params, $stmt);
		}

		# Get results from statement
		$results = $this->getResultsFromStmt($stmt);

		# Update affected rows from query result
		$this->affectedRows = $stmt->affected_rows;

		$stmt->close();

		$this->closeNoLock($db);

		return $results;
	}

	/** @inheritdoc */
	public function getAffectedRows() {
		return $this->affectedRows;
	}

	#region Connections and Constructor
	/**
	 * If ping returns false or throws an exception
	 * it will try to reopen connection
	 * @throws DatabaseException if openConnection fails
	 */
	protected function reConnect() {
		try {
			if (!$this->dbMaster->ping()) {
				$this->dbMaster = $this->openConnection($this->dbMasterConnection);
			}
		} catch (\Exception $ex) {
			$this->dbMaster = $this->openConnection($this->dbMasterConnection);
		}

		if ($this->dbSlave === null) {
			return;
		}
		try {
			if (!$this->dbSlave->ping()) {
				$this->dbSlave = $this->openConnection($this->dbSlaveConnection);
			}
		} catch (\Exception $ex) {
			$this->dbSlave = $this->openConnection($this->dbSlaveConnection);
		}
	}

	/**
	 * Set the dbMaster attribute, to allow mocking for testing
	 * @param \mysqli $dbMaster
	 */
	protected function setDbMaster($dbMaster) {
		$this->dbMaster = $dbMaster;
	}

	/**
	 * Set the dbSlave attribute, to allow mocking for testing
	 * @param \mysqli $dbSlave
	 */
	protected function setDbSlave($dbSlave) {
		$this->dbSlave = $dbSlave;
	}

	/**
	 * @param DbConnection         $connection
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(DbConnection $connection, LoggerInterface $logger = null) {
		$this->dbMasterConnection = $connection;
		$this->dbSlaveConnection = $connection->getSlave();
		$this->setLogger($logger ?: new NullLogger());

		$this->dbMaster = $this->openConnection($this->dbMasterConnection);
		$this->dbSlave = $this->openConnection($this->dbSlaveConnection);
	}

	/**
	 * Creates a \mysqli connection from DbConnection and verifies the connection is established.
	 * If connection is invalid a DatabaseException is thrown. Returns null if $connection is null
	 * @param DbConnection $connection
	 * @throws DatabaseException
	 * @return \mysqli|null
	 */
	private function openConnection(DbConnection $connection = null) {
		if ($connection == null) {
			return null;
		}

		$mysqli = new \mysqli(
			$connection->getHost(),
			$connection->getUser(),
			$connection->getPassword(),
			$connection->getSchema(),
			$connection->getPort());

		if ($mysqli == null || !$mysqli->ping()) {
			throw new DatabaseException("Unable to establish connection to database");
		}

		return $mysqli;
	}

	public function close() {
		if ($this->dbMaster) {
			@$this->dbMaster->close();
		}
		if ($this->dbSlave) {
			@$this->dbSlave->close();
		}
	}

	public function __destruct() {
		$this->close();
	}
	#endregion

	#region Query Helper methods

	#region Master Slave determination
	/**
	 * Returns either the master or slave db connection based on the query being run
	 * @param string $query
	 * @return \mysqli
	 */
	protected function chooseDbByQuery($query) {
		if ($this->dbSlave == null) {
			return $this->dbMaster;
		}
		if ($this->forceMaster) {
			return $this->dbMaster;
		}

		if (String::startsWithInsensitive(ltrim($query), 'select ') && !$this->isSelectIntoQuery($query)) {
			return $this->dbSlave;
		}

		return $this->dbMaster;
	}

	/**
	 * Determines whether or not the query is a SELECT INTO type query
	 * @param string $query
	 * @return boolean
	 */
	private function isSelectIntoQuery($query) {
		return String::containsInsensitive($query, 'into outfile') ||
		       String::containsInsensitive($query, 'into dumpfile');
	}
	#endregion

	#region Parameter binding
	/**
	 * Bind parameters to a statement
	 * @param $stmt
	 * @param $params
	 * @return \mysqli_stmt
	 */
	private function bindParamsToStmt(\mysqli_stmt $stmt, $params) {
		if (empty($params)) {
			return $stmt;
		}

		$bindParams = $this->getParamsWithTypeString($params);

		call_user_func_array(array( $stmt, "bind_param" ), $this->refValues($bindParams));

		return $stmt;
	}

	/**
	 * Prepends a string based on param types for the bind_param function.
	 * Also converts booleans and DateTimes to database formats.
	 * @param array $params
	 * @return array
	 */
	private function getParamsWithTypeString($params) {
		$types = ''; //initial string with types
		for ($i = 0; $i < count($params); $i++) {
			if (is_int($params[$i])) {
				$types .= 'i';
			} elseif (is_float($params[$i])) {
				$types .= 'd';
			} elseif (is_string($params[$i])) {
				$types .= 's';
			} elseif (is_bool($params[$i])) {
				$params[$i] = $params[$i] ? 1 : 0;
				$types .= 'i';
			} elseif ($params[$i] instanceof \DateTime) {
				/** @var \DateTime $dt */
				$dt = $params[$i];
				$params[$i] = $dt->format('Y-m-d H:i:s');
				$types .= 's';
			} else {
				$types .= 'b'; //blob and unknown
			}
		}
		array_unshift($params, $types); # prepend type string
		return $params;
	}

	/**
	 * Corrects param array for bind_param function
	 * @param array $arr
	 * @return array
	 */
	private function refValues($arr) {
		$refs = array();
		foreach ($arr as $key => $value) {
			$refs[$key] = & $arr[$key];
		}
		return $refs;
	}
	#endregion

	/**
	 * Returns results from a statement
	 * @param \mysqli_stmt $stmt
	 * @return array
	 */
	private function getResultsFromStmt(\mysqli_stmt $stmt) {
		# Get metadata for field names
		$meta = $stmt->result_metadata();

		# Return no results
		if (!$meta) {
			return array();
		}

		# Dynamically create an array of variables to use to bind the results
		$fields = array();
		while ($field = $meta->fetch_field()) {
			$var = $field->name;
			$$var = null;
			$fields[$var] = & $$var;
		}

		# Bind Results
		call_user_func_array(array( $stmt, 'bind_result' ), $fields);

		# Fetch Results
		$i = 0;
		$results = array();
		while ($stmt->fetch()) {
			$results[$i] = array();
			foreach ($fields as $k => $v) {
				$results[$i][$k] = $v;
			}
			$i++;
		}
		$meta->free();
		return $results;
	}

	/**
	 * Throw DatabaseException and log error
	 * @param string               $msg
	 * @param string               $query
	 * @param array                $params
	 * @param \mysqli|\mysqli_stmt $db
	 * @throws DatabaseException
	 */
	private function throwDbException($msg, $query, $params, $db) {
		$this->log->error($msg, array(
			"query" => $query,
			"params" => $params,
			"error" => $db->error,
			"errorNum" => $db->errno
		));
		throw new DatabaseException($db->error, $db->errno);
	}

	private function openNoLock(\mysqli $db) {
		if ($this->usingNoLock) {
			$db->query('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
		}
	}

	private function closeNoLock(\mysqli $db) {
		if (!$this->usingNoLock) { return; }
		$db->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
		$this->usingNoLock = false;
	}

	#endregion
}
