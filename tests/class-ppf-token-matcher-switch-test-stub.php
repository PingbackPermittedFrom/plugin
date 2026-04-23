<?php
/**
 * Test stub exposing matcher internals.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use PPF\Pingback\PPF_Token_Matcher_Switch;
use PPF\Pingback\PPF_Record;

/**
 * Test wrapper to expose protected matcher methods.
 *
 * @phpstan-import-type ResolveResult from PPF_Record
 */
class PPF_Token_Matcher_Switch_Test_Stub extends PPF_Token_Matcher_Switch {
	/**
	 * Forced evaluate_record result.
	 *
	 * @var bool|null
	 */
	private $forced_record_result = null;

	/**
	 * Forced resolve status for evaluate_record.
	 *
	 * @var ResolveResult|null
	 */
	private $forced_resolve_result = null;

	/**
	 * Set forced evaluate_record result.
	 *
	 * @param bool|null          $record_result Result of evaluate_record.
	 * @param ResolveResult|null $resolve_result Result of resolve.
	 * @return void
	 */
	public function set_forced_evaluate_record( $record_result, $resolve_result ) {
		$this->forced_record_result  = $record_result;
		$this->forced_resolve_result = $resolve_result;
	}

	/**
	 * Clear forced evaluate_record result.
	 *
	 * @return void
	 */
	public function clear_forced_evaluate_record() {
		$this->forced_record_result  = null;
		$this->forced_resolve_result = null;
	}

	/**
	 * Expose evaluate_record for testing.
	 *
	 * @param string|null        $host           Hostname.
	 * @param string             $sender_ip      Sender IP address.
	 * @param int                $level          Current include depth.
	 * @param ResolveResult|null $resolve_result Result of evaluation (by reference).
	 * @return bool|null
	 */
	public function evaluate_record_public( $host, $sender_ip, $level, &$resolve_result = null ) {
		return $this->evaluate_record( $host, $sender_ip, $level, $resolve_result );
	}

	/**
	 * Expose match_token for testing.
	 *
	 * @param string $token     Token string.
	 * @param string $sender_ip Sender IP address.
	 * @param string $host      Hostname for evaluation context.
	 * @param int    $level     Current include depth.
	 * @return bool|null
	 */
	public function match_token_public( $token, $sender_ip, $host, $level = 0 ) {
		return $this->match_token( $token, $sender_ip, $host, $level );
	}

	/**
	 * Expose match_tokens for testing.
	 *
	 * @param string[] $tokens    Token strings.
	 * @param string   $sender_ip Sender IP address.
	 * @param string   $host      Hostname for evaluation context.
	 * @param int      $level     Current include depth.
	 * @return bool|null
	 */
	public function match_tokens_public( $tokens, $sender_ip, $host, $level = 0 ) {
		return $this->match_tokens( $tokens, $sender_ip, $host, $level );
	}

	/**
	 * Expose match_a for testing.
	 *
	 * @param string      $host     Hostname for evaluation.
	 * @param string|null $override Override hostname from token.
	 * @param string      $sender_ip Sender IP address.
	 * @return true|null
	 */
	public function match_a_public( $host, $override, $sender_ip ) {
		return $this->match_a( $host, $override, $sender_ip );
	}

	/**
	 * Expose match_ip4 for testing.
	 *
	 * @param string|null $range     Range or IP.
	 * @param string      $sender_ip Sender IP address.
	 * @return true|null
	 */
	public function match_ip4_public( $range, $sender_ip ) {
		return $this->match_ip4( $range, $sender_ip );
	}

	/**
	 * Expose match_ip6 for testing.
	 *
	 * @param string|null $range     Range or IP.
	 * @param string      $sender_ip Sender IP address.
	 * @return true|null
	 */
	public function match_ip6_public( $range, $sender_ip ) {
		return $this->match_ip6( $range, $sender_ip );
	}

	/**
	 * Override to return forced result when set; otherwise delegate to parent.
	 *
	 * @param string|null        $host           Hostname.
	 * @param string             $sender_ip      Sender IP address.
	 * @param int                $level          Current include depth.
	 * @param ResolveResult|null $resolve_result Result of evaluation (by reference).
	 * @return bool|null
	 */
	protected function evaluate_record( $host, $sender_ip, $level, &$resolve_result = null ) {
		if ( null !== $this->forced_resolve_result ) {
			$resolve_result = $this->forced_resolve_result;
			return $this->forced_record_result;
		}

		return parent::evaluate_record( $host, $sender_ip, $level, $resolve_result );
	}
}
