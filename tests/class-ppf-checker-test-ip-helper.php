<?php
/**
 * Test IP helper for PPF checker.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use PPF\Pingback\PPF_IP_Helper;

/**
 * Test double returning a fixed remote IP.
 */
class PPF_Checker_Test_IP_Helper extends PPF_IP_Helper {
	/**
	 * Remote IP to return.
	 *
	 * @var string|false
	 */
	private $remote_ip;

	/**
	 * Create the test helper with a fixed remote IP.
	 *
	 * @param string|false $remote_ip Remote IP to return.
	 */
	public function __construct( $remote_ip ) {
		$this->remote_ip = $remote_ip;
	}

	/**
	 * Return the configured remote IP.
	 *
	 * @param string       $pagelinkedfrom URI of the page linked from.
	 * @param string       $pagelinkedto   URI of the page linked to.
	 * @param array<mixed> $args           Original XML-RPC arguments.
	 * @return string|false
	 */
	public function get_remote_ip( $pagelinkedfrom, $pagelinkedto, $args ) {
		return $this->remote_ip;
	}
}
