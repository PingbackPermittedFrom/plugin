<?php
/**
 * Pingback Permitted From (PPF)
 *
 * Default resolver for record checks.
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

require_once __DIR__ . '/class-ppf-record-resolver.php';

/**
 * Default resolver for WordPress runtime.
 */
class PPF_Record_Default_Resolver implements PPF_Record_Resolver {
	/**
	 * Number of lookups performed.
	 *
	 * @var int
	 */
	protected $lookup_count = 0;

	/**
	 * Get TXT records for a host.
	 *
	 * @param string   $hostname Hostname to query.
	 * @param int|null $resolver_error Resolver error code.
	 * @return string|false|null TXT record, false if too many lookups, null on resolver error.
	 */
	public function get_txt_records( $hostname, &$resolver_error = null ) {
		$resolver_error = self::ERROR_NONE;

		++$this->lookup_count;
		if ( $this->lookup_count > 10 ) {
			$resolver_error = self::ERROR_TOO_MANY_LOOKUPS;
			return false;
		}

		$cache_key = 'ppf_txt_' . md5( $hostname );

		$cached = wp_cache_get( $cache_key, 'ppf_pingback' );
		if ( false !== $cached ) {
			// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Type assertion for cache return.
			/** @var string|null $cached */
			return $cached;
		}

		$records = @dns_get_record( $hostname, DNS_TXT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $records ) {
			$resolver_error = self::ERROR_UNKNOWN;
			return null;
		} elseif ( 0 === count( $records ) ) {
			$resolver_error = self::ERROR_NO_RECORDS;
			return null;
		} elseif ( 1 < count( $records ) ) {
			$resolver_error = self::ERROR_TOO_MANY_RECORDS;
			return false;
		}

		$record = $records[0];
		if ( isset( $record['entries'] ) && is_array( $record['entries'] ) ) {
			/**
			 * Record entries.
			 *
			 * @var array<int, string> $entries
			 */
			$entries = $record['entries'];
			$txt     = implode( '', $entries );
		} elseif ( isset( $record['txt'] ) ) {
			$txt = $record['txt'];
		} else {
			$resolver_error = self::ERROR_INVALID_RECORD;
			return null;
		}

		wp_cache_set( $cache_key, $txt, 'ppf_pingback', 3600 );

		return $txt;
	}

	/**
	 * Get host IPs for a host.
	 *
	 * @param string $hostname Hostname to query.
	 * @return string[]|false|null Host IPs, false if too many lookups, null on resolver error.
	 */
	public function get_host_ips( $hostname ) {
		if ( '' === $hostname ) {
			return false;
		}

		$cache_key = 'ppf_ip_' . md5( $hostname );

		$cached = wp_cache_get( $cache_key, 'ppf_pingback' );
		if ( false !== $cached ) {
			// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- Type assertion for cache return.
			/** @var string[]|null $cached */
			return $cached;
		}

		$records = @dns_get_record( $hostname, DNS_A | DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $records ) {
			return null;
		}

		$ips = array();
		foreach ( $records as $record ) {
			if ( isset( $record['ip'] ) ) {
				$ips[] = $record['ip'];
				continue;
			}
			if ( isset( $record['ipv6'] ) ) {
				$ips[] = $record['ipv6'];
			}
		}

		wp_cache_set( $cache_key, $ips, 'ppf_pingback', 3600 );

		return $ips;
	}
}
