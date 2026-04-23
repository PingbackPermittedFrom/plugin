<?php
/**
 * Pingback Permitted From (PPF)
 *
 * IP helpers for PPF authorization.
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

/**
 * Matches IP addresses and resolves remote IPs.
 */
class PPF_IP_Helper {
	/**
	 * Match IPv4 against a single IP or CIDR range.
	 *
	 * @param string $ip IPv4 address.
	 * @param string $range Range or IP.
	 * @return bool
	 */
	public function matches_ipv4( $ip, $range ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}

		if ( false === strpos( $range, '/' ) ) {
			return $ip === $range;
		}

		list( $subnet, $mask ) = explode( '/', $range, 2 );
		if ( ! filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}

		$mask = (int) $mask;
		if ( $mask < 0 || $mask > 32 ) {
			return false;
		}

		if ( 0 === $mask ) {
			return true;
		}

		$ip_long     = ip2long( $ip );
		$subnet_long = ip2long( $subnet );
		$mask_long   = -1 << ( 32 - $mask );

		return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
	}

	/**
	 * Match IPv6 against a single IP or CIDR range.
	 *
	 * @param string $ip IPv6 address.
	 * @param string $range Range or IP.
	 * @return bool
	 */
	public function matches_ipv6( $ip, $range ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return false;
		}

		if ( false === strpos( $range, '/' ) ) {
			return $ip === $range;
		}

		list( $subnet, $mask ) = explode( '/', $range, 2 );
		if ( ! filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return false;
		}

		$mask = (int) $mask;
		if ( $mask < 0 || $mask > 128 ) {
			return false;
		}

		$ip_bin     = inet_pton( $ip );
		$subnet_bin = inet_pton( $subnet );
		if ( false === $ip_bin || false === $subnet_bin ) {
			return false; // @codeCoverageIgnore
		}

		$bytes     = intdiv( $mask, 8 );
		$remainder = $mask % 8;

		if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
			return false;
		}

		if ( 0 === $remainder ) {
			return true;
		}

		$mask_byte = ( 0xFF << ( 8 - $remainder ) ) & 0xFF;
		$ip_byte   = ord( $ip_bin[ $bytes ] ) & $mask_byte;
		$sub_byte  = ord( $subnet_bin[ $bytes ] ) & $mask_byte;

		return $ip_byte === $sub_byte;
	}

	/**
	 * Get the remote IP address for PPF authorization.
	 *
	 * Checks the filter first, then HTTP_X_FORWARDED_FOR from trusted proxies, then REMOTE_ADDR.
	 *
	 * @param string       $pagelinkedfrom URI of the page linked from.
	 * @param string       $pagelinkedto   URI of the page linked to.
	 * @param array<mixed> $args           Original XML-RPC arguments.
	 * @return string|false Remote IP address, or false if not available.
	 *
	 * @phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	 */
	public function get_remote_ip( $pagelinkedfrom, $pagelinkedto, $args ) {
		/**
		 * Allows overriding the remote IP address used for PPF authorization.
		 *
		 * @param string|false $remote_ip      Validated remote IP address (pre-authorization) or false if not available.
		 * @param string       $pagelinkedfrom URI of the page linked from.
		 * @param string       $pagelinkedto   URI of the page linked to.
		 * @param array<mixed> $args           Original XML-RPC arguments.
		 */
		$raw_ip = apply_filters( 'ppf_pingback_remote_ip', false, $pagelinkedfrom, $pagelinkedto, $args );

		// If the filter returns a valid IP, use it.
		if ( false !== $raw_ip ) {
			if ( filter_var( $raw_ip, FILTER_VALIDATE_IP ) === $raw_ip ) {
				return $raw_ip;
			} else {
				_doing_it_wrong( __METHOD__, 'Invalid remote IP address returned by filter.', '1.0.0' );
				$raw_ip = false;
			}
		}

		/**
		 * Use filter_var instead of filter_input so we can use the $_SERVER superglobal in unit tests.
		 *
		 * @phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- This is the validation path.
		 */
		if ( isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ) {
			$raw_ip = wp_unslash( $_SERVER['REMOTE_ADDR'] );
		}

		if (
			isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) &&
			is_string( $_SERVER['HTTP_X_FORWARDED_FOR'] ) &&
			is_string( $raw_ip ) &&
			$this->is_trusted_proxy( $raw_ip )
		) {
			$forwarded_for   = wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$forwarded_parts = explode( ',', $forwarded_for );
			$raw_ip          = trim( $forwarded_parts[0] );
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return filter_var( $raw_ip, FILTER_VALIDATE_IP );
	}

	/**
	 * Check whether the direct peer is a trusted proxy.
	 *
	 * @param string $remote_addr Direct peer IP address.
	 * @return bool
	 */
	protected function is_trusted_proxy( $remote_addr ) {
		if ( ! filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		if ( ! defined( 'PPF_TRUSTED_PROXIES' ) ) {
			return false;
		}

		$trusted_proxies = constant( 'PPF_TRUSTED_PROXIES' );
		if ( ! is_array( $trusted_proxies ) ) {
			_doing_it_wrong( __METHOD__, 'PPF_TRUSTED_PROXIES must be an array of CIDR ranges or IP addresses.', '1.0.0' );
			return false;
		}

		foreach ( $trusted_proxies as $range ) {
			if ( ! is_string( $range ) || '' === $range ) {
				continue;
			}

			if (
				$this->matches_ipv4( $remote_addr, $range ) ||
				$this->matches_ipv6( $remote_addr, $range )
			) {
				return true;
			}
		}

		return false;
	}
}
