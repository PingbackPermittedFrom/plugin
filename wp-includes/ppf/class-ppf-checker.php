<?php
/**
 * Pingback Permitted From (PPF)
 *
 * @author  Charles Lecklider
 * @license GPL-2.0+
 * @link    https://ppf1.org/
 * @package pingback-permitted-from
 */

namespace PPF\Pingback;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-ppf-advisory.php';
require_once __DIR__ . '/class-ppf-logger.php';
require_once __DIR__ . '/class-ppf-ip-helper.php';
require_once __DIR__ . '/class-ppf-record.php';
require_once __DIR__ . '/class-ppf-token-matcher-switch.php';

/**
 * Checks pingback authorization flow.
 *
 * @phpstan-import-type CheckResult from PPF_Result
 */
class PPF_Checker {

	/**
	 * IP helper instance.
	 *
	 * @var PPF_IP_Helper
	 */
	protected $ip_helper;
	/**
	 * Record helper instance.
	 *
	 * @var PPF_Record
	 */
	protected $record;
	/**
	 * Token matcher instance.
	 *
	 * @var PPF_Token_Matcher_Switch
	 */
	protected $matcher;

	/**
	 * Last authorization result.
	 *
	 * @var string|null One of the PPF_Result::* constants, or null if no authorization has been performed.
	 */
	protected $result = null;

	/**
	 * Create a checker.
	 *
	 * @param PPF_IP_Helper|null            $ip_helper IP helper.
	 * @param PPF_Token_Matcher_Switch|null $matcher Matcher instance.
	 * @param PPF_Record|null               $record Record helper.
	 */
	public function __construct( ?PPF_IP_Helper $ip_helper = null, ?PPF_Token_Matcher_Switch $matcher = null, ?PPF_Record $record = null ) {
		$this->ip_helper = $ip_helper instanceof PPF_IP_Helper ? $ip_helper : new PPF_IP_Helper();
		$this->record    = $record instanceof PPF_Record ? $record : new PPF_Record();
		$this->matcher   = $matcher instanceof PPF_Token_Matcher_Switch ? $matcher : $this->create_matcher();
	}

	/**
	 * Create a default matcher.
	 *
	 * @return PPF_Token_Matcher_Switch
	 */
	protected function create_matcher() {
		return new PPF_Token_Matcher_Switch( $this->record, $this->ip_helper );
	}

	/**
	 * Check the pingback.
	 *
	 * @param string       $pagelinkedfrom URI of the page linked from.
	 * @param string       $pagelinkedto   URI of the page linked to, or post ID if it is a trackback.
	 * @param array<mixed> $args           Original XML-RPC arguments.
	 *
	 * @return bool|string|\WP_Error
	 */
	public function check( $pagelinkedfrom, $pagelinkedto, $args = array() ) {
		$remote_ip = $this->ip_helper->get_remote_ip( $pagelinkedfrom, $pagelinkedto, $args );
		$host      = $this->parse_url_host( $pagelinkedfrom );

		/* We cannot continue if the sender IP is missing or the source URL host is invalid. */
		if ( false === $remote_ip || false === $host ) {
			return ( $this->is_strict_mode() ) ? new \WP_Error( 51, 'PPF authorization failed.' ) : false;
		}

		/**
		 * Filters whether the pingback source is unwelcome before authorization.
		 *
		 * Allows security plugins to block pingbacks based on host, IP, or other criteria
		 * before PPF authorization is performed.
		 *
		 * @param bool        $source_unwelcome Whether the pingback source is unwelcome.
		 * @param string|false $remote_ip        Remote IP address (pre-authorization) or false if not available.
		 * @param string      $host             Source hostname (normalized) or empty string if invalid.
		 * @param string      $pagelinkedfrom   URI of the page linked from.
		 * @param string      $pagelinkedto     URI of the page linked to, or post ID if it is a trackback.
		 * @param array       $args             Original XML-RPC arguments.
		 */
		$source_unwelcome = apply_filters( 'ppf_pingback_unwelcome', false, $remote_ip, $host, $pagelinkedfrom, $pagelinkedto, $args );
		if ( true === $source_unwelcome ) {
			// TODO: Remove after prototyping work is done.
			PPF_Logger::log_message(
				'Source URL rejected by filter.',
				array(
					'host'       => $host,
					'remote_ip'  => $remote_ip,
					'source_url' => $pagelinkedfrom,
					'target_url' => $pagelinkedto,
				)
			);
			return new \WP_Error( 52, 'Source unwelcome.' );
		}

		/**
		 * Filters whether to skip PPF authorization.
		 *
		 * @param bool        $skip_authorization Whether to skip PPF authorization.
		 * @param string      $pagelinkedfrom   URI of the page linked from.
		 * @param string      $pagelinkedto     URI of the page linked to, or post ID if it is a trackback.
		 * @param array       $args             Original XML-RPC arguments.
		 */
		$skip_authorization = apply_filters( 'ppf_authorization_skip', false, $pagelinkedfrom, $pagelinkedto, $args );
		if ( true === $skip_authorization ) {
			// TODO: Remove after prototyping work is done.
			PPF_Logger::log_message(
				'Skipping PPF authorization.',
				array(
					'source_url' => $pagelinkedfrom,
					'target_url' => $pagelinkedto,
				)
			);
		}
		if ( false === $skip_authorization ) {
			$this->result = $this->authorize( $host, $remote_ip );

			// TODO: Remove after prototyping work is done.
			PPF_Logger::log_message(
				'PPF authorization evaluated.',
				array(
					'result'    => $this->result,
					'remote_ip' => $remote_ip,
				)
			);

			/**
			 * Filters the PPF authorization result after evaluation.
			 *
			 * @param string|\WP_Error      $result     Authorization result after evaluation.
			 * @param string      $sender_ip  Sender IP address used for evaluation.
			 * @param string      $host       Source hostname.
			 * @param string|null $record     PPF record string used for evaluation.
			 */
			$result = apply_filters( 'ppf_authorization_result', $this->result, $remote_ip, $host, $this->record->get_record() );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			switch ( $result ) {
				case PPF_Result::NO_MATCH:
					$this->result = $result;
					// TODO: Remove after prototyping work is done.
					PPF_Logger::log_message( 'PPF authorization failed: no match.', array() );
					return new \WP_Error( 51, 'PPF authorization failed (51). See <https://ppf1.org/rejected/#51> for more information.' );
				case PPF_Result::NO_RECORD: // Fall through.
				case PPF_Result::INVALID: // Fall through.
				case PPF_Result::ERROR:
					$this->result = $result;
					if ( $this->is_strict_mode() ) {
						// TODO: Remove after prototyping work is done.
						PPF_Logger::log_message( 'Strict mode enabled with missing/invalid PPF record.' );
						return new \WP_Error( 18, 'No PPF record exists (18). See <https://ppf1.org/rejected/#18> for more information.' );
					}
					break;
				case PPF_Result::MATCH:
					new PPF_Advisory();
					// Fall through.
				case PPF_Result::NONE: // Fall through.
				case PPF_Result::UNKNOWN:
					$this->result = $result;
					break;
				default:
					_doing_it_wrong( __FUNCTION__, 'Invalid authorization result.', '1.0.0' );
					return new \WP_Error( 0, 'Invalid authorization result.' );
			}
		}

		/**
		 * Filters the remote IP address after PPF authorization.
		 *
		 * @param string|\WP_Error $remote_ip      Remote IP address (pre-authorization) or WP_Error if authorization failed.
		 * @param string           $host           Source hostname (normalized) or empty string if invalid.
		 * @param string           $pagelinkedfrom URI of the page linked from.
		 * @param string           $pagelinkedto   URI of the page linked to, or post ID if it is a trackback.
		 * @param array            $args           Original XML-RPC arguments.
		 */
		$remote_ip = apply_filters( 'ppf_authorization_after', $remote_ip, $host, $pagelinkedfrom, $pagelinkedto, $args );

		return $remote_ip;
	}

	/**
	 * Parse and return host from a URL.
	 *
	 * @param string $url Input URL.
	 * @return string|false Hostname or false if invalid.
	 */
	public function parse_url_host( $url ) {
		if ( '' === $url ) {
			return false;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $parts['host'] ) || '' === $parts['host'] ) {
			return false;
		}

		$host = strtolower( $parts['host'] );
		$host = rtrim( $host, '.' );
		return $host;
	}

	/**
	 * Determine if strict mode is enabled via wp-config.php.
	 *
	 * @return bool
	 */
	public function is_strict_mode() {
		return defined( 'PPF_STRICT_MODE' ) && constant( 'PPF_STRICT_MODE' );
	}

	/**
	 * Get the last authorization result.
	 *
	 * @return string One of the PPF_Result::* constants.
	 */
	public function get_result() {
		return $this->result ?? PPF_Result::UNKNOWN;
	}

	/**
	 * Authorize a sender IP against a source host's PPF policy.
	 *
	 * @param string $host       Source hostname (normalized).
	 * @param string $sender_ip  Validated sender IP address.
	 * @return CheckResult One of the PPF_Result::* constants.
	 */
	protected function authorize( $host, $sender_ip ) {
		$result = $this->matcher->evaluate( $host, $sender_ip );
		if ( PPF_Result::NO_RECORD === $result ) {
			// TODO: Remove after prototyping work is done.
			PPF_Logger::log_message( 'No PPF record found for host.', array( 'host' => $host ) );
			return PPF_Result::NO_RECORD;
		}

		if ( PPF_Result::INVALID === $result ) {
			// TODO: Remove after prototyping work is done.
			PPF_Logger::log_message( 'Invalid PPF record for host.', array( 'host' => $host ) );
			return PPF_Result::INVALID;
		}

		// TODO: Remove after prototyping work is done.
		PPF_Logger::log_message(
			'PPF authorization result.',
			array(
				'host'      => $host,
				'sender_ip' => $sender_ip,
				'result'    => $result,
			)
		);
		return $result;
	}
}
