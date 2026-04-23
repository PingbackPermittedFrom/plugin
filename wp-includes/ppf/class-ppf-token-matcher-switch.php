<?php
/**
 * Pingback Permitted From (PPF)
 *
 * Token matcher with switch-based evaluation.
 *
 * @author  Charles Lecklider
 * @license GPL-2.0+
 * @link    https://ppf1.org/
 * @package pingback-permitted-from
 */

declare(strict_types=1);

namespace PPF\Pingback;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-ppf-ip-helper.php';
require_once __DIR__ . '/class-ppf-record.php';

/**
 * Evaluates PPF tokens using a switch statement.
 *
 * @phpstan-import-type CheckResult from PPF_Result
 * @phpstan-import-type ResolveResult from PPF_Record
 */
class PPF_Token_Matcher_Switch {
	/**
	 * Max include recursion depth.
	 *
	 * @var int
	 */
	private const MAX_DEPTH = 5;

	/**
	 * IP matcher helper.
	 *
	 * @var PPF_IP_Helper
	 */
	private $ip_matcher;

	/**
	 * Record helper.
	 *
	 * @var PPF_Record
	 */
	private $record_helper;

	/**
	 * Create matcher.
	 *
	 * @param PPF_Record    $record_helper Record helper.
	 * @param PPF_IP_Helper $ip_matcher IP helper.
	 */
	public function __construct( PPF_Record $record_helper, PPF_IP_Helper $ip_matcher ) {
		$this->record_helper = $record_helper;
		$this->ip_matcher    = $ip_matcher;
	}

	/**
	 * Evaluate a host's record and tokens.
	 *
	 * @param string $host Hostname.
	 * @param string $sender_ip Sender IP address.
	 * @return CheckResult
	 */
	public function evaluate( $host, $sender_ip ) {
		$record_result = $this->evaluate_record( $host, $sender_ip, 1, $resolve_result );
		switch ( $resolve_result ) {
			case PPF_Record::STATUS_OK:
				switch ( true ) {
					case true === $record_result:
						return PPF_Result::MATCH;
					case false === $record_result:
						return PPF_Result::NONE;
					default:
						return PPF_Result::NO_MATCH;
				}
			case PPF_Record::STATUS_NO_RECORD:
				return PPF_Result::NO_RECORD;
			case PPF_Record::STATUS_INVALID:
				return PPF_Result::INVALID;
		}

		return PPF_Result::INVALID; // @codeCoverageIgnore
	}

	/**
	 * Match include mechanisms.
	 *
	 * @param string|null        $host Hostname.
	 * @param string             $sender_ip Sender IP address.
	 * @param int                $level Current include depth.
	 * @param ResolveResult|null $resolve_result Result of evaluation.
	 * @return bool|null True for match, false for explicit rejection, null for no match.
	 */
	protected function evaluate_record( $host, $sender_ip, $level, &$resolve_result = null ) {
		/* Invalid host: invalid record */
		if ( null === $host ) {
			return false;
		}

		/* Too deep: invalid record */
		if ( $level > self::MAX_DEPTH ) {
			return false;
		}

		$resolve_result = $this->record_helper->resolve( $host );

		if ( PPF_Record::STATUS_OK === $resolve_result ) {
			$tokens = $this->record_helper->get_tokens();
			return $this->match_tokens( $tokens, $sender_ip, $host, $level );
		}

		return null;
	}

	/**
	 * Evaluate tokens for a host.
	 *
	 * @param string[] $tokens Token list.
	 * @param string   $sender_ip Sender IP address.
	 * @param string   $host Hostname for evaluation.
	 * @param int      $level Current include depth.
	 * @return bool|null
	 */
	protected function match_tokens( $tokens, $sender_ip, $host, $level ) {
		foreach ( $tokens as $token ) {
			$result = $this->match_token( $token, $sender_ip, $host, $level );
			if ( is_bool( $result ) ) {
				return $result;
			}
		}

		return null;
	}

	/**
	 * Evaluate a token.
	 *
	 * @param string $token Token string.
	 * @param string $sender_ip Sender IP address.
	 * @param string $host Hostname for evaluation context.
	 * @param int    $level Current include depth.
	 * @return bool|null True for match, false for explicit rejection, null for no match.
	 */
	protected function match_token( $token, $sender_ip, $host, $level = 0 ) {
		$token = trim( $token );
		if ( '' === $token ) {
			return null;
		}

		$parts = explode( ':', $token, 2 );
		$type  = strtolower( $parts[0] );
		$value = $parts[1] ?? null;

		switch ( $type ) {
			case 'none':
				return false;
			case 'a':
				return $this->match_a( $host, $value, $sender_ip );
			case 'ip4':
				return $this->match_ip4( $value, $sender_ip );
			case 'ip6':
				return $this->match_ip6( $value, $sender_ip );
			case 'include':
				return $this->evaluate_record( $value, $sender_ip, $level + 1 );
			default:
				return null;
		}
	}

	/**
	 * Match "a" mechanisms.
	 *
	 * @param string      $host Hostname for evaluation.
	 * @param string|null $override Override hostname from token.
	 * @param string      $sender_ip Sender IP address.
	 * @return true|null
	 */
	protected function match_a( $host, $override, $sender_ip ) {
		$target_host = ( null === $override ) ? $host : $override;
		$target_host = $this->normalize_host( $target_host );

		$ips = $this->record_helper->get_host_ips( $target_host );
		if ( is_array( $ips ) && in_array( $sender_ip, $ips, true ) ) {
			return true;
		}

		return null;
	}

	/**
	 * Match ip4 mechanisms.
	 *
	 * @param string|null $range Range or IP.
	 * @param string      $sender_ip Sender IP address.
	 * @return true|null
	 */
	protected function match_ip4( $range, $sender_ip ) {
		return ( null !== $range && $this->ip_matcher->matches_ipv4( $sender_ip, $range ) )
			? true
			: null;
	}

	/**
	 * Match ip6 mechanisms.
	 *
	 * @param string|null $range Range or IP.
	 * @param string      $sender_ip Sender IP address.
	 * @return true|null
	 */
	protected function match_ip6( $range, $sender_ip ) {
		return ( null !== $range && $this->ip_matcher->matches_ipv6( $sender_ip, $range ) )
			? true
			: null;
	}

	/**
	 * Normalize a hostname for comparisons.
	 *
	 * @param string $host Hostname.
	 * @return string
	 */
	protected function normalize_host( $host ) {
		$host = strtolower( trim( $host ) );
		return rtrim( $host, '.' );
	}
}
