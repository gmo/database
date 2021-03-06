<?php
namespace Gmo\Database;

use Gmo\Common\Str;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Database abstraction designed to provide basic query functions.
 * 
 * @package GMO\Database
 *
 * @since 1.4.0 Added Slave Connection.
 *              Added getInsertId().
 *              Throwing DatabaseException.
 * @since 1.2.0 Added DbConnection
 * @since 1.1.0 Added getAffectedRows() method
 * @since 1.0.0
 */
abstract class AbstractDatabase implements LoggerAwareInterface {

	#region Variables
	/** @var \mysqli */
	private $dbMaster;
	/** @var \mysqli */
	private $dbSlave;

	/** @var \GMO\Database\DbConnection */
	private $dbMasterConnection;
	/** @var \GMO\Database\DbConnection|null */
	private $dbSlaveConnection;

	/** @var int number of affected rows from last query */
	private $affectedRows;

	/** @var bool Force master connection flag */
	private $forceMaster;

	/** @var LoggerInterface */
	protected $log;
	#endregion

	#region Public methods
	/**
	 * Runs sql scripts to setup database tables
	 * @param string $path directory without ending slash
	 */
	public function runScriptsFromDir( $path ) {
		$path = realpath( $path );

		$this->log->info( "========================" );
		$this->log->info( "Running scripts in:   " . $path . "/*.sql" );
		$files = glob( $path . "/*.sql" );
		$this->log->info( "Number scripts found: " . count( $files ) );
		$this->log->info( "========================" );

		foreach ( $files as $file ) {
			$data = file_get_contents( $file );

			// Remove C style and inline comments
			$comment_patterns = array(
				'/\/\*.*(\n)*.*(\*\/)?/', //C comments
				'/\s*--.*\n/', //inline comments start with --
				'/\s*#.*\n/', //inline comments start with #
			);
			$data = preg_replace( $comment_patterns, "\n", $data );

			//Retrieve sql statements
			$stmts = explode( ";", $data );
			$stmts = preg_replace( '#[^\S\n]#', " ", $stmts );
			$stmts = preg_replace( "[\n]", "\n\t", $stmts );

			foreach ( $stmts as $query ) {
				if ( trim( $query ) == "" ) {
					continue;
				}
				$this->log->info( "Executing query:\n\t" . $query );
				$this->reConnect();
				$db = $this->chooseDbByQuery( $query );
				$db->query( $query );
				if ( $db->errno == 0 ) {
					$this->log->info( "Execution: SUCCESS" );
				} elseif ( $db->errno == 1060 ) // Duplicate column
				{
					$this->log->warning( "Execution: " . $db->error );
				} else {
					$this->log->error( "Execution: " . $db->error );
				}
				$this->log->info( "============" );
			}
		}
	}

	/**
	 * Sets a logger instance on the object
	 * @param LoggerInterface $logger
	 * @return null
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->log = $logger;
	}
	#endregion

	#region Query methods
	/**
	 * Force next query to use master database connection
	 * @return $this
	 */
	protected function useMaster() {
		$this->forceMaster = true;
		return $this;
	}

	/**
	 * Returns a single value from the first column
	 * of the first row of results from query.
	 * @param string $query
	 * @param mixed  $params variable number
	 * @throws DatabaseException if query fails
	 * @return mixed
	 */
	protected function singleValue( $query, $params = null ) {
		# execute
		$result = call_user_func_array( array( $this, "singleRow" ), func_get_args() );
		# return first value
		$value = array_shift( $result );
		return $value;
	}

	/**
	 * Returns a key/value array of the first row
	 * of results from query.
	 * @param string $query
	 * @param mixed  $params variable number
	 * @throws DatabaseException if query fails
	 * @return array
	 */
	protected function singleRow( $query, $params = null ) {
		# execute
		$results = call_user_func_array( array( $this, "execute" ), func_get_args() );
		# return results
		if ( empty($results) ) {
			return array();
		}
		return $results[0];
	}

	/**
	 * Returns a list of a single column.
	 *
	 * @example keyValueArray("SELECT value FROM Foo");
	 *
	 * @param string $query
	 * @param mixed  $params variable number
	 * @return array array( value1, value2 )
	 */
	protected function singleColumn($query, $params = null) {
		$result = call_user_func_array(array($this, "execute"), func_get_args());
		return array_map(function($row) { return reset($row); }, $result);
	}

	/**
	 * Returns a key value array.
	 * This will deduplicate the data based on the key.
	 * If the query only has one column the keys are set to the values.
	 * If the query has more than two columns the entire row is used for the values.
	 *
	 * @example keyValueArray("SELECT id, value FROM Foo");
	 *
	 * @param string $query
	 * @param mixed  $params variable number
	 * @return array array( id1 => value1, id2 => value2 )
	 */
	protected function keyValueArray($query, $params = null) {
		$data = call_user_func_array(array($this, "execute"), func_get_args());
		$result = array();
		foreach ($data as $row) {
			if (count($row) === 1) {
				$value = reset($row);
			} elseif (count($row) > 2) {
				$value = $row;
			} else {
				$value = end($row);
			}
			$result[reset($row)] = $value;
		}
		return $result;
	}

	/**
	 * Executes a query wrapped in no lock statement
	 * @param string $query
	 * @param mixed  $params variable number
	 * @throws DatabaseException if query fails
	 * @return array
	 */
	protected function selectWithNoLock( $query, $params = null ) {
		$this->chooseDbByQuery($query)->query( "SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED" );
		$results = call_user_func_array( array( $this, "execute" ), func_get_args() );
		$this->chooseDbByQuery($query)->query( "SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ" );

		return $results;
	}

	/**
	 * Inserts a row and returns the id
	 * @param string $query
	 * @param mixed  $params variable number
	 * @throws DatabaseException if query fails
	 * @return int
	 */
	protected function insertAndReturnId( $query, $params = null ) {
		$this->chooseDbByQuery($query)->query( "start transaction" );
		call_user_func_array( array( $this, "execute" ), func_get_args() );
		$id = $this->getInsertId();
		$this->chooseDbByQuery($query)->query( "commit" );

		return $id;
	}
	
	/**
	 * Returns the last insert id on the master db connection
	 * @return int
	 */
	protected function getInsertId() {
		return $this->dbMaster->insert_id;
	}

	/**
	 * Returns a list of key/value arrays of results
	 * from query.
	 * @param string $query
	 * @param mixed  $params variable number
	 * @throws DatabaseException if query fails
	 * @return array
	 */
	protected function execute( $query, $params = null ) {
		# Get query and params
		$params = func_get_args();
		$query = array_shift( $params );

		# Update query and params with params that have arrays.
		list($query, $params) = $this->expandQueryParams( $query, $params );

		$this->reConnect();

		# Create statement
		$db = $this->chooseDbByQuery($query);
		$this->forceMaster = false;

		$connection = $db->thread_id === $this->dbMaster->thread_id ? "master" : "slave";
		$this->log->debug($query, array('params' => $params, 'connection' => $connection));

		$stmt = $db->prepare( $query );
		if ( !$stmt ) {
			$this->throwDbException("Error preparing statement", $query, $params, $db);
		}

		$stmt = $this->bindParamsToStmt($stmt, $params);

		# Execute query
		if ( !$stmt->execute() ) {
			$this->throwDbException("Error executing statement", $query, $params, $stmt);
		}

		# Get results from statement
		$results = $this->getResultsFromStmt( $stmt );

		# Update affected rows from query result
		$this->affectedRows = $stmt->affected_rows;

		$stmt->close();

		return $results;
	}

	/**
	 * Gets the number of affected rows from last query
	 * @return int number of affected rows
	 */
	protected function getAffectedRows() {
		return $this->affectedRows;
	}
	#endregion

	#region Connections and Constructor
	/**
	 * If ping returns false or throws an exception
	 * it will try to reopen connection
	 * @throws DatabaseException if openConnection fails
	 */
	protected function reConnect() {
		try {
			if ( !$this->dbMaster->ping() ) {
				$this->dbMaster = $this->openConnection($this->dbMasterConnection);
			}
		} catch ( \Exception $ex ) {
			$this->dbMaster = $this->openConnection($this->dbMasterConnection);
		}

		if ($this->dbSlave === null) {
			return;
		}
		try {
			if ( !$this->dbSlave->ping() ) {
				$this->dbSlave = $this->openConnection($this->dbSlaveConnection);
			}
		} catch ( \Exception $ex ) {
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
	 * @param DbConnection|mixed   $connection Takes a DbConnection or host, user, password, schema
	 * @param LoggerInterface|null $logger
	 * @param DbConnection         $slaveConnection
	 * @throws \InvalidArgumentException
	 */
	function __construct($connection, $logger = null, $slaveConnection = null) {
		$args = func_get_args();

		if ($args[0] instanceof DbConnection) {
			$this->dbMasterConnection = $args[0];
			$this->dbSlaveConnection = $args[0]->getSlave();
			if (isset($args[1])) {
				$this->log = $args[1];
			}
		} elseif (count($args) == 4 || count($args) == 5) {
			$this->dbMasterConnection = new DbConnection($args[1], $args[2], $args[0], $args[3]);
			if (isset($args[4])) {
				$this->log = $args[4];
			}
		} else {
			throw new \InvalidArgumentException();
		}

		if ($slaveConnection) {
			$this->dbSlaveConnection = $slaveConnection;
		}

		if ($this->log == null) {
			$this->log = new NullLogger();
		}

		$this->dbMaster = $this->openConnection($this->dbMasterConnection);
		$this->dbSlave = $this->openConnection($this->dbSlaveConnection);
	}

	/**
	 * Creates a \mysqli connection from DbConnection and verifies the connection is established.
	 * If connection is invalid a DatabaseException is thrown. Returns null if $connnection is null
	 * @param DbConnection $connection
	 * @throws DatabaseException
	 * @return \mysqli|null
	 */
	private function openConnection(DbConnection $connection = null) {
		if($connection == null) {
			return null;
		}

		$mysqli = new \mysqli(
			$connection->getHost(),
			$connection->getUser(),
			$connection->getPassword(),
			$connection->getSchema(),
			$connection->getPort());

		if ( $mysqli == null || !$mysqli->ping() ) {
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

		if (Str::startsWith(ltrim($query), 'select ', true) && !$this->isSelectIntoQuery($query)) {
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
		return Str::contains($query, 'into outfile', true) ||
		       Str::contains($query, 'into dumpfile', true);
	}
	#endregion

	#region Preparing query
	/**
	 * Expands all params that are arrays into the main params
	 * array and updates query string with the correct number
	 * of question marks and removes tabs and new lines.
	 * @param string $query
	 * @param array  $params
	 * @return array (query, params)
	 */
	private function expandQueryParams( $query, $params ) {
		# remove tabs and new lines
		$query = preg_replace( "/[\t|\n| ]+/", " ", $query );

		$newParams = array();
		# Check each param for arrays
		foreach ( $params as $param ) {
			if ( is_array( $param ) ) {
				# Get string of question marks for query
				$marks = $this->getQuestionMarks( $param );

				# Replace the first occurrence of "??" with correct number of "?"
				$query = preg_replace( "/\\?\\?/", $marks, $query, 1 );

				# Add each item in array to new params
				foreach ( $param as $item ) {
					$newParams[] = $item;
				}
			} else {
				# If not array, just add to new params
				$newParams[] = $param;
			}
		}
		return array( $query, $newParams );
	}

	/**
	 * Returns a string of question marks based on
	 * number of variables in the array passed in
	 */
	private function getQuestionMarks( $params ) {
		$string = "";
		for ( $i = 0; $i < count( $params ); $i++ ) {
			$string .= "?, ";
		}
		# remove the last ", "
		$string = substr( $string, 0, -2 );

		return $string;
	}
	#endregion

	#region Parameter binding
	/**
	 * Bind parameters to a statement
	 * @param $stmt
	 * @param $params
	 * @return \mysqli_stmt
	 */
	private function bindParamsToStmt($stmt, $params) {
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
	private function getParamsWithTypeString( $params ) {
		$types = ''; //initial string with types
		for ($i = 0; $i < count($params); $i++) {
			if ( is_int( $params[$i] ) ) {
				$types .= 'i';
			} elseif ( is_float( $params[$i] ) ) {
				$types .= 'd';
			} elseif ( is_string( $params[$i] ) ) {
				$types .= 's';
			} elseif ( is_bool( $params[$i] ) ) {
				$params[$i] = $params[$i] ? 1 : 0;
				$types .= 'i';
			} elseif ( $params[$i] instanceof \DateTime ) {
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
	private function refValues( $arr ) {
		$refs = array();
		foreach ( $arr as $key => $value ) {
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
	private function getResultsFromStmt( $stmt ) {
		# Get metadata for field names
		$meta = $stmt->result_metadata();

		# Return no results
		if ( !$meta ) {
			return array();
		}

		# Dynamically create an array of variables to use to bind the results
		$fields = array();
		while ( $field = $meta->fetch_field() ) {
			$var = $field->name;
			$$var = null;
			$fields[$var] = & $$var;
		}

		# Bind Results
		call_user_func_array( array( $stmt, 'bind_result' ), $fields );

		# Fetch Results
		$i = 0;
		$results = array();
		while ( $stmt->fetch() ) {
			$results[$i] = array();
			foreach ( $fields as $k => $v ) {
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

	#endregion
}
