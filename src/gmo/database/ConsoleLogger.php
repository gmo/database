<?php
namespace gmo\database;

use Psr\Log\AbstractLogger;

class ConsoleLogger extends AbstractLogger {
	/**
	 * Logs with an arbitrary level.
	 * @param mixed  $level
	 * @param string $message
	 * @param array  $context
	 * @return null
	 */
	public function log( $level, $message, array $context = array() ) {
		$extra = empty($context) ? "" : " " . json_encode($context);
		echo $message . $extra . "\n";
	}
}