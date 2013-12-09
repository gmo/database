<?php
namespace GMO\Database;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Database abstraction designed to provide basic query functions.
 *
 * @package GMO\Database
 *
 * @since 1.1.0 Added getAffectedRows() method
 * @since 1.0.0
 */
abstract class AbstractDatabase implements LoggerAwareInterface {

	/**
	 * @var \mysqli
	 */
	private $db_user;

	private $host;
	private $username;
	private $password;
	private $database;

	/** @var int number of affected rows from last query */
	private $affectedRows;

	protected $log;

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
			$stmts = explode( ";\n", $data );
			$stmts = preg_replace( "/\\s/", " ", $stmts );

			foreach ( $stmts as $query ) {
				if ( trim( $query ) == "" ) {
					continue;
				}
				$this->log->info( "Executing query: " . $query );
				$this->reConnect();
				$result = $this->db_user->query( $query );

				$errno = $this->db_user->errno;
				$errorMsg = $this->db_user->error;
				if ( $errno == 0 ) {
					$this->log->info( "Execution: SUCCESS" );
				} elseif ( $errno = 1060 ) // Duplicate column
				{
					$this->log->info( "Execution: WARNING: " . $errorMsg );
				} else {
					$this->log->info( "Execution: ERROR: " . $errorMsg );
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

	/**
	 * Returns a single value from the first column
	 * of the first row of results from query.
	 * @param string $query
	 * @param mixed  $params variable number
	 * @throws \Exception if query fails
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
	 * @throws \Exception if query fails
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
	 * Executes a query wrapped in no lock statement
	 * @param string $query
	 * @param mixed  $params variable number
	 * @throws \Exception if query fails
	 * @return array
	 */
	protected function selectWithNoLock( $query, $params = null ) {
		$this->db_user->query( "SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED" );
		$results = call_user_func_array( array( $this, "execute" ), func_get_args() );
		$this->db_user->query( "SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ" );

		return $results;
	}

	/**
	 * Inserts a row and returns the id
	 * @param string $query
	 * @param mixed  $params variable number
	 * @throws \Exception if query fails
	 * @return array
	 */
	protected function insertAndReturnId( $query, $params = null ) {
		$this->db_user->query( "start transaction" );
		call_user_func_array( array( $this, "execute" ), func_get_args() );
		$id = $this->singleValue( "select last_insert_id() as id" );
		$this->db_user->query( "commit" );

		return $id;
	}

	/**
	 * Returns a list of key/value arrays of results
	 * from query.
	 * @param string $query
	 * @param mixed  $params variable number
	 * @throws \Exception if query fails
	 * @return array
	 */
	protected function execute( $query, $params = null ) {
		# Get query and params
		$params = func_get_args();
		$query = array_shift( $params );

		$query = preg_replace( "/[\t|\n| ]+/", " ", $query );

		# Update query and params with params that have arrays
		list($query, $params) = $this->expandQueryParams( $query, $params );

		$this->reConnect();

		# Create statement
		$stmt = $this->db_user->prepare( $query );
		if ( !$stmt ) {
			throw new \Exception("Error preparing statement. Query: \"$query\"");
		}

		if ( !empty($params) ) {
			# Get types for bind_param
			$type = $this->getParamTypes( $params );
			array_unshift( $params, $type );

			# Bind variables to the statement
			call_user_func_array( array( $stmt, "bind_param" ), $this->refValues( $params ) );
		}

		# Execute query
		if ( !$stmt->execute() ) {
			throw new \Exception("MySql Error - Code: " . $stmt->errno . ". " . $stmt->error);
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

	/**
	 * If ping returns false or throws an exception
	 * it will try to reopen connection
	 * @throws \Exception if openConnection fails
	 */
	protected function reConnect() {
		try {
			if ( !$this->db_user->ping() ) {
				$this->openConnection();
			}
		} catch ( \Exception $ex ) {
			$this->openConnection();
		}
	}

	/**
	 * @param DbConnection|mixed $connection Takes a DbConnection or host, user, password, schema
	 * @throws \InvalidArgumentException
	 */
	function __construct($connection) {
		$args = func_get_args();

		if (count($args) == 1 && $args[0] instanceof DbConnection) {
			/** @var DbConnection $dbConn */
			$dbConn = $args[0];
			$this->host = $dbConn->getHost();
			$this->username = $dbConn->getUser();
			$this->password = $dbConn->getPassword();
			$this->database = $dbConn->getSchema();
		} elseif (count($args) == 4) {
			$this->host = $args[0];
			$this->username = $args[1];
			$this->password = $args[2];
			$this->database = $args[3];
		} else {
			throw new \InvalidArgumentException();
		}

		$this->log = new NullLogger();

		$this->openConnection();
	}

	/**
	 * Creates \mysqli connection
	 * @throws \Exception if invalid connection
	 */
	private function openConnection() {
		$this->db_user = new \mysqli($this->host, $this->username, $this->password, $this->database);

		if ( $this->db_user == null || !$this->db_user->ping() ) {
			throw new \Exception("Unable to establish connection to database");
		}
	}

	/**
	 * Makes a string based on param types
	 * for the bind_param function
	 * @param array $params
	 * @return string
	 */
	private function getParamTypes( &$params ) {
		$types = ''; //initial sting with types
		for ($i = 0; $i < count($params); $i++) {
			if ( is_int( $params[$i] ) ) {
				$types .= 'i'; //integer
			} elseif ( is_float( $params[$i] ) ) {
				$types .= 'd'; //double
			} elseif ( is_string( $params[$i] ) ) {
				$types .= 's'; //string
			} elseif ( is_bool( $params[$i] ) ) {
				$params[$i] = $params[$i] ? 1 : 0;
				$types .= 'i';
			} else {
				$types .= 'b'; //blob and unknown
			}
		}
		return $types;
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

	/**
	 * Expands all params that are arrays into the main params
	 * array and updates query string with the correct number
	 * of question marks for the bind_param function
	 * @param string $query
	 * @param array  $params
	 * @return array (query, params)
	 */
	private function expandQueryParams( $query, $params ) {
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
}