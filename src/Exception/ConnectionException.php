<?php
namespace GMO\Database\Exception;

/**
 * Class ConnectionException
 * @package GMO\Database\Exception
 * @since 2.0.0
 */
class ConnectionException extends DatabaseException {

	public function __construct($message = "", $code = 0, \Exception $previous = null) {
		parent::__construct($message ?: "Unable to establish connection to database", $code, $previous);
	}
}
