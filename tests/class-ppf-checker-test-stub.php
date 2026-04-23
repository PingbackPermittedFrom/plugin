<?php
/**
 * Test stub for PPF checker that exposes protected methods.
 *
 * @package PPF_Pingback_Direct
 */

namespace PPF\Pingback\Tests;

use PPF\Pingback\PPF_Checker;
use PPF\Pingback\PPF_IP_Helper;
use PPF\Pingback\PPF_Record;
use PPF\Pingback\PPF_Token_Matcher_Switch;

/**
 * Stub class that exposes protected methods for testing.
 */
class PPF_Checker_Test_Stub extends PPF_Checker {
	/**
	 * Create a stub checker.
	 *
	 * @param PPF_IP_Helper|null            $ip_helper IP helper.
	 * @param PPF_Token_Matcher_Switch|null $matcher Matcher instance.
	 * @param PPF_Record|null               $record Record helper.
	 */
	public function __construct( ?PPF_IP_Helper $ip_helper = null, ?PPF_Token_Matcher_Switch $matcher = null, ?PPF_Record $record = null ) { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Stub; parent call required.
		parent::__construct( $ip_helper, $matcher, $record );
	}

	/**
	 * Expose authorize() as public for testing.
	 *
	 * @param string $host      Source hostname (normalized).
	 * @param string $sender_ip Validated sender IP address.
	 * @return string One of the RESULT_* constants.
	 */
	public function authorize( $host, $sender_ip ) { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Stub to expose protected method.
		return parent::authorize( $host, $sender_ip );
	}
}
