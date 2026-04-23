<?php
/**
 * Pingback Permitted From (PPF)
 *
 * Resolver interface for record checks.
 *
 * @author  Charles Lecklider
 * @license GPL-2.0+
 * @link    https://ppf1.org/
 * @package pingback-permitted-from
 */

namespace PPF\Pingback;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Defines resolver dependencies for record checks.
 */
interface PPF_Record_Resolver {

	const ERROR_NONE             = 0;
	const ERROR_UNKNOWN          = 1;
	const ERROR_TOO_MANY_LOOKUPS = 2;
	const ERROR_TOO_MANY_RECORDS = 3;
	const ERROR_INVALID_RECORD   = 4;
	const ERROR_NO_RECORDS       = 5;

	/**
	 * Resolve TXT records for a hostname.
	 *
	 * @param string $hostname Hostname to query.
	 * @param int    $resolver_error Resolver error code.
	 * @return string|false|null TXT payload, false on non-recoverable resolver conditions (e.g. too many records), null when none or on resolver error.
	 */
	public function get_txt_records( $hostname, &$resolver_error = self::ERROR_NONE );

	/**
	 * Resolve IPs for a hostname.
	 *
	 * @param string $hostname Hostname to query.
	 * @return string[] Array of IPs.
	 */
	public function get_host_ips( $hostname );
}
