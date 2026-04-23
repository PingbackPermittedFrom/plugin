<?php
/**
 * Fake resolver for PPF record tests.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use PPF\Pingback\PPF_Record_Resolver;

/**
 * Fake resolver for deterministic TXT and DNS responses.
 */
class PPF_Record_Fake_Resolver implements PPF_Record_Resolver {
	/**
	 * Hostnames where TXT lookup simulates multiple DNS TXT records (false, ERROR_TOO_MANY_RECORDS).
	 *
	 * @var array<string, true>
	 */
	private $txt_too_many = array();

	/**
	 * TXT records keyed by hostname.
	 *
	 * @var array<string, string|false|null>
	 */
	private $txt_records = array();

	/**
	 * Resolver errors keyed by hostname.
	 *
	 * @var array<string, int>
	 */
	private $txt_record_errors = array();

	/**
	 * Host IPs keyed by hostname.
	 *
	 * @var array<string, string[]>
	 */
	private $host_ips = array();

	/**
	 * Register TXT record value for a hostname (single merged string, as returned by the real resolver).
	 *
	 * @param string            $hostname Hostname.
	 * @param string|false|null $records  TXT payload; null or false as returned by {@see get_txt_records()}.
	 * @return void
	 */
	public function set_txt_records( $hostname, $records ) {
		$key = strtolower( $hostname );
		unset( $this->txt_too_many[ $key ] );
		$this->txt_records[ $key ] = $records;
	}

	/**
	 * Simulate multiple distinct TXT RRs for a hostname (resolver returns false, ERROR_TOO_MANY_RECORDS).
	 *
	 * @param string $hostname Hostname.
	 * @return void
	 */
	public function set_txt_simulate_multiple_records( $hostname ) {
		$key = strtolower( $hostname );
		unset( $this->txt_records[ $key ] );
		$this->txt_too_many[ $key ] = true;
	}

	/**
	 * Register resolver error code for a TXT lookup hostname.
	 *
	 * @param string $hostname Hostname.
	 * @param int    $error    Resolver error code.
	 * @return void
	 */
	public function set_txt_record_error( $hostname, int $error ): void {
		$this->txt_record_errors[ strtolower( $hostname ) ] = $error;
	}

	/**
	 * Register host IPs for a hostname.
	 *
	 * @param string   $hostname Hostname.
	 * @param string[] $ips      IP addresses.
	 * @return void
	 */
	public function set_host_ips( $hostname, $ips ) {
		$this->host_ips[ strtolower( $hostname ) ] = $ips;
	}

	/**
	 * Get TXT records for a host.
	 *
	 * @param string $hostname Hostname to query.
	 * @param int    $resolver_error Resolver error code.
	 * @return string|false|null
	 */
	public function get_txt_records( $hostname, &$resolver_error = PPF_Record_Resolver::ERROR_NONE ) {
		$key            = strtolower( $hostname );
		$resolver_error = PPF_Record_Resolver::ERROR_NONE;

		if ( isset( $this->txt_too_many[ $key ] ) ) {
			$resolver_error = PPF_Record_Resolver::ERROR_TOO_MANY_RECORDS;
			return false;
		}

		if ( array_key_exists( $key, $this->txt_record_errors ) ) {
			$resolver_error = $this->txt_record_errors[ $key ];
		}

		if ( array_key_exists( $key, $this->txt_records ) ) {
			return $this->txt_records[ $key ];
		}

		return null;
	}

	/**
	 * Get host IPs for a host.
	 *
	 * @param string $hostname Hostname to query.
	 * @return string[]
	 */
	public function get_host_ips( $hostname ) {
		$key = strtolower( $hostname );
		if ( array_key_exists( $key, $this->host_ips ) ) {
			return $this->host_ips[ $key ];
		}

		return array();
	}
}
