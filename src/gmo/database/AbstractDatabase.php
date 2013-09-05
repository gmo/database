<?php
namespace GMO\Database;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractDatabase implements LoggerAwareInterface {

    /**
     * @var \mysqli
     */
    private $db_user;

    private $host;
    private $username;
    private $password;
    private $database;

	private $logger;

	/**
	 * @param $host
	 * @param $username
	 * @param $password
	 * @param $database
	 */
	function __construct($host, $username, $password, $database) {
		$this->host     = $host;
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;

		$this->logger = new ConsoleLogger();

        $this->openConnection();
	}

	/**
	 * Returns a single value from the first column
	 * of the first row of results from query.
	 * @param string $query
	 * @param mixed $params variable number
	 * @throws \Exception Error preparing statement
	 * @return array
	 */
	protected function singleValue($query, $params=null) {
		# execute
		$result = call_user_func_array(array($this, "singleRow"), func_get_args());
		# return first value
		$value = array_shift($result);
		return $value;
	}

	/**
	 * Returns a key/value array of the first row
	 * of results from query.
	 * @param string $query
	 * @param mixed $params variable number
	 * @throws \Exception Error preparing statement
	 * @return array
	 */
	protected function singleRow($query, $params=null) {
		# execute
		$results = call_user_func_array(array($this, "execute"), func_get_args());
		# return results
		if (empty($results)) {
			return array();
		}
		return $results[0];
	}

	/**
	 * Executes a query wrapped in no lock statement
	 * @param string $query
	 * @param mixed $params variable number
	 * @throws \Exception Error preparing statement
	 * @return array
	 */
	protected function selectWithNoLock($query, $params=null) {
		$this->db_user->query("SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");
		$results = call_user_func_array(array($this, "execute"), func_get_args());
		$this->db_user->query("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");

		return $results;
	}

    protected function insertAndReturnId($query, $params=null) {
        $this->db_user->query("start transaction");
        call_user_func_array(array($this, "execute"), func_get_args());
        $id = $this->singleValue("select last_insert_id() as id");
        $this->db_user->query("commit");

        return $id;
    }

    /**
	 * Returns a list of key/value arrays of results
	 * from query.
	 * @param string $query
	 * @param mixed $params variable number
	 * @throws \Exception Error preparing statement
	 * @return array
	 */
	protected function execute($query, $params=null) {
		# Get query and params
		$params = func_get_args();
		$query = array_shift($params);

		$query = preg_replace("/[\t|\n| ]+/", " ", $query);

		# Update query and params with params that have arrays
		list($query, $params) = $this->expandQueryParams($query, $params);

        $this->reConnect();

		# Create statement
		$stmt = $this->db_user->prepare($query);
		if (!$stmt) {
			throw new \Exception("Error preparing statement. Query: \"$query\"");
		}

		if (!empty($params)) {
			# Get types for bind_param
			$type = $this->getParamTypes($params);
			array_unshift($params, $type);

			# Bind variables to the statement
			call_user_func_array(array($stmt, "bind_param"), $this->refValues($params));
		}

		# Execute query
		if( !$stmt->execute() ) {
			throw new \Exception( "MySql Error - Code: " . $stmt->errno . ". " . $stmt->error );
		}


		# Get results from statement
		$results = $this->getResultsFromStmt($stmt);

		$stmt->close();

		return $results;
	}

    protected function reConnect()
    {
        try
        {
            if( !$this->db_user->ping() )
            {
                $this->openConnection();
            }
        }
        catch( \Exception $ex )
        {
            $this->openConnection();
        }
    }

    private function openConnection()
    {
        $this->db_user = new \mysqli( $this->host, $this->username, $this->password, $this->database );

        if( $this->db_user == null || !$this->db_user->ping() )
        {
            throw new \Exception( "Unable to establish connection to database" );
        }
    }

    /**
	 * Makes a string based on param types
	 * for the bind_param function
	 * @param array $params
	 * @return string
	 */
	private function getParamTypes($params) {
		$types = '';                        //initial sting with types
		foreach($params as $param)
		{
			if(is_int($param)) {
				$types .= 'i';              //integer
			} elseif (is_float($param)) {
				$types .= 'd';              //double
			} elseif (is_string($param)) {
				$types .= 's';              //string
			} else {
				$types .= 'b';              //blob and unknown
			}
		}
		return $types;
	}

	/**
	 * Corrects param array for bind_param function
	 * @param array $arr
	 * @return array
	 */
	private function refValues($arr) {
		$refs = array();
		foreach ($arr as $key => $value)
		{
			$refs[$key] = &$arr[$key];
		}
		return $refs;
	}

	/**
	 * Expands all params that are arrays into the main params
	 * array and updates query string with the correct number
	 * of question marks for the bind_param function
	 * @param string $query
	 * @param array $params
	 * @return array (query, params)
	 */
	private function expandQueryParams($query, $params) {
		$newParams = array();
		# Check each param for arrays
		foreach ($params as $param) {
			if (is_array($param)) {
				# Get string of question marks for query
				$marks = $this->getQuestionMarks($param);

				# Replace the first occurrence of "??" with correct number of "?"
				$query = preg_replace("/\\?\\?/", $marks, $query, 1);

				# Add each item in array to new params
				foreach ($param as $item) {
					$newParams[] = $item;
				}
			} else {
				# If not array, just add to new params
				$newParams[] = $param;
			}
		}
		return array($query, $newParams);
	}

	/**
	 * Returns a string of question marks based on
	 * number of variables in the array passed in
	 */
	private function getQuestionMarks($params) {
		$string = "";
		for ($i=0; $i < count($params); $i++) {
			$string .= "?, ";
		}
		# remove the last ", "
		$string = substr($string, 0, -2);

		return $string;
	}

	/**
	 * Returns results from a statement
	 * @param \mysqli_stmt $stmt
	 * @return array
	 */
	private function getResultsFromStmt($stmt) {
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
		call_user_func_array(array($stmt, 'bind_result'), $fields);

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
	 * Runs sql scripts to setup database tables
	 * @param string $path directory without ending slash
	 */
	public function runScriptsFromDir($path) {
		$path = realpath($path);

        $this->logger->info("========================");
		$this->logger->info("Running scripts in:   ". $path . "/*.sql");
		$files = glob($path . "/*.sql");
		$this->logger->info("Number scripts found: " . count($files));
        $this->logger->info("========================");

		foreach ($files as $file) {
			$data = file_get_contents($file);

			// Remove C style and inline comments
			$comment_patterns = array('/\/\*.*(\n)*.*(\*\/)?/', //C comments
			                          '/\s*--.*\n/', //inline comments start with --
			                          '/\s*#.*\n/', //inline comments start with #
			);
			$data = preg_replace($comment_patterns, "\n", $data);

			//Retrieve sql statements
			$stmts = explode(";\n", $data);
			$stmts = preg_replace("/\\s/", " ", $stmts);

			foreach ($stmts as $query) {
				if (trim($query) == "") {
					continue;
				}
				$this->logger->info("Executing query: " . $query);
                $this->reConnect();
				$result = $this->db_user->query($query);

                $errno = $this->db_user->errno;
                $errorMsg = $this->db_user->error;
                if( $errno == 0 )
                {
                   $this->logger->info( "Execution: SUCCESS");
                }
                elseif( $errno = 1060 ) // Duplicate column
                {
                    $this->logger->info( "Execution: WARNING: " . $errorMsg );
                }
                else
                {
                    $this->logger->info( "Execution: ERROR: " . $errorMsg );
                }
                $this->logger->info( "============" );
			}
		}
	}

	/**
	 * Sets a logger instance on the object
	 * @param LoggerInterface $logger
	 * @return null
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}
}