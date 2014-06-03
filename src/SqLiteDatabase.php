<?php
namespace GMO\Database;

use GMO\Common\String;
use GMO\Database\Connection\DbConnection;
use GMO\Database\Exception\ConnectionException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * SqLite implementation of {@see IDatabase}.
 *
 * Handles binding parameters consistent with {@see MysqlDatabase}.
 *
 * {@see IDatabase::useMaster() useMaster()},
 * {@see IDatabase::withNoLock() withNoLock()}, and
 * {@see IDatabase::withTransaction() withTransaction()}
 * maintain the interface but are stubbed out.
 *
 * {@see \GMO\Database\SqLiteDatabase::getAffectedRows() getAffectedRows()}
 * differs from MySQL as well.
 *
 * @package GMO\Database
 * @since 2.0.0
 */
class SqLiteDatabase extends AbstractDatabase {

	/** @var \SqLite3 */
	private $db;
	private $affectedRows;

	/**
	 * Does nothing.
	 * @return $this
	 */
	public function useMaster() {
		return $this;
	}

	/**
	 * Does nothing.
	 * @return $this
	 */
	public function withNoLock() {
		return $this;
	}

	/**
	 * Does nothing.
	 * @param $callback callable with IDatabase argument passed to it
	 * @return mixed
	 */
	public function withTransaction(callable $callback) {
		return $callback($this);
	}

	/** @inheritdoc */
	public function getInsertId() {
		return $this->db->lastInsertRowID();
	}

	/**
	 * Returns the number of database rows that were changed
	 * (or inserted or deleted) by the most recent SQL statement
	 * @return int
	 */
	public function getAffectedRows() {
		return $this->affectedRows;
	}

	protected function expandQueryParams($query, $params) {
		if (String::contains($query, 'INSERT IGNORE', false)) {
			$query = str_replace('INSERT IGNORE', 'INSERT OR IGNORE', $query);
		}
		return parent::expandQueryParams($query, $params);
	}

	/** @inheritdoc */
	public function execute($query, $params = null) {
		# Get query and params
		$params = func_get_args();
		$query = array_shift($params);

		# Update query and params with params that have arrays.
		list($query, $params) = $this->expandQueryParams($query, $params);

		$this->log->debug($query, array('params' => $params));

		$stmt = $this->db->prepare($query);
		if (!$stmt) {
			$this->throwQueryException("Error preparing statement", $query, $params,
			                        $this->db->lastErrorMsg(), $this->db->lastErrorCode());
		}
		$stmt = $this->bindParamsToStmt($stmt, $params);

		$resultStmt = $stmt->execute();
		if (!$resultStmt) {
			$this->throwQueryException("Error executing statement", $query, $params,
			                           $this->db->lastErrorMsg(), $this->db->lastErrorCode());
		}
		$stmt->close();

		$results = $this->getResultsFromStmt($resultStmt);

		$this->affectedRows = $this->db->changes();

		return $results;
	}

	/**
	 * @param DbConnection|string|null $connection Accepts filename or
	 *                                             {@see DbConnection} and uses the
	 *                                             {@see DbConnection::getSchema schema} property.
	 *                                             Uses in memory by default.
	 * @param LoggerInterface|null     $logger
	 * @throws Exception\ConnectionException
	 */
	public function __construct($connection = ':memory:', LoggerInterface $logger = null) {
		$filename = $connection instanceof DbConnection ? $connection->getSchema() : $connection;

		$this->setLogger($logger ?: new NullLogger());

		try {
			$this->db = new \SQLite3($filename);
		} catch (\Exception $e) {
			throw new ConnectionException();
		}
	}

	/**
	 * Bind parameters to a statement
	 * @param $stmt
	 * @param $params
	 * @return \SQLite3Stmt
	 */
	private function bindParamsToStmt(\SQLite3Stmt $stmt, $params) {
		$i = 1;
		foreach ($params as $param) {
			if (is_bool($param)) {
				$param = $param ? 1 : 0;
			} elseif ($param instanceof \DateTime) {
				/** @var \DateTime $dt */
				$dt = $param;
				$param = $dt->format('Y-m-d H:i:s');
			}
			$stmt->bindValue($i++, $param);
		}

		return $stmt;
	}

	/**
	 * Returns results from a statement
	 * @param \SQLite3Result $resultStmt
	 * @return array
	 */
	private function getResultsFromStmt(\SQLite3Result $resultStmt) {
		$results = array();
		if ($resultStmt->numColumns() == 0) {
			return $results;
		}
		while($row = $resultStmt->fetchArray(SQLITE3_ASSOC)) {
			$results[] = $row;
		}
		$resultStmt->finalize();
		return $results;
	}
}
