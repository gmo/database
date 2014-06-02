<?php
namespace GMO\Database;

use Psr\Log\LoggerInterface;

/**
 * @package GMO\Database
 */
abstract class AbstractDatabase implements IDatabase {

	/** @var bool Force master connection flag */
	protected $forceMaster = false;
	/** @var bool Using no lock flag */
	protected $usingNoLock = false;

	/** @var LoggerInterface */
	protected $log;

	/** @inheritdoc */
	public function setLogger(LoggerInterface $logger) {
		$this->log = $logger;
	}

	/** @inheritdoc */
	public function useMaster() {
		$this->forceMaster = true;
		return $this;
	}

	/** @inheritdoc */
	public function withNoLock() {
		$this->usingNoLock = true;
		return $this;
	}

	/** @inheritdoc */
	public function singleValue($query, $params = null) {
		# execute
		$result = call_user_func_array(array( $this, "singleRow" ), func_get_args());
		# return first value
		$value = array_shift($result);
		return $value;
	}

	/** @inheritdoc */
	public function singleRow($query, $params = null) {
		# execute
		$results = call_user_func_array(array( $this, "execute" ), func_get_args());
		# return results
		if (empty($results)) {
			return array();
		}
		return $results[0];
	}

	/** @inheritdoc */
	public function singleColumn($query, $params = null) {
		$result = call_user_func_array(array( $this, "execute" ), func_get_args());
		return array_map(function ($row) { return reset($row); }, $result);
	}

	/** @inheritdoc */
	public function keyValueArray($query, $params = null) {
		$data = call_user_func_array(array( $this, "execute" ), func_get_args());
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

	/** @inheritdoc */
	public function insertAndReturnId($query, $params = null) {
		$args = func_get_args();
		return $this->withTransaction(function (IDatabase $db) use ($args) {
			call_user_func_array(array( $db, "execute" ), func_get_args());
			return $db->getInsertId();
		});
	}

	/**
	 * Expands all params that are arrays into the main params
	 * array and updates query string with the correct number
	 * of question marks and removes tabs and new lines.
	 * @param string $query
	 * @param array  $params
	 * @return array (query, params)
	 */
	protected function expandQueryParams($query, $params) {
		# remove tabs and new lines
		$query = preg_replace("/[\t|\n| ]+/", " ", $query);

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
		return array( $query, $newParams );
	}

	/**
	 * Returns a string of question marks based on
	 * number of variables in the array passed in
	 */
	private function getQuestionMarks($params) {
		$string = "";
		for ($i = 0; $i < count($params); $i++) {
			$string .= "?, ";
		}
		# remove the last ", "
		$string = substr($string, 0, -2);

		return $string;
	}
}
