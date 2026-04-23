<?php
/**
 * Test matcher for PPF checker.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use PPF\Pingback\PPF_Token_Matcher_Switch;

/**
 * Test double returning a fixed evaluate result.
 */
class PPF_Checker_Test_Matcher extends PPF_Token_Matcher_Switch {
	/**
	 * CheckResult to return.
	 *
	 * @var string
	 */
	private $result;

	/**
	 * Create the test matcher with a fixed result.
	 *
	 * @param string $result CheckResult to return.
	 */
	public function __construct( $result ) {
		$this->result = $result;
	}

	/**
	 * Return the configured result.
	 *
	 * @param string|null $host      Hostname.
	 * @param string      $sender_ip Sender IP address.
	 * @return string CheckResult
	 */
	public function evaluate( $host, $sender_ip ) {
		return $this->result;
	}
}
