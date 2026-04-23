<?php
/**
 * Pingback Permitted From (PPF)
 *
 * Handles fetching and parsing PPF DNS records.
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
require_once __DIR__ . '/class-ppf-record-default-resolver.php';

/**
 * Handles retrieving and parsing PPF DNS records.
 *
 * @phpstan-type ResolveResult PPF_Record::STATUS_OK|PPF_Record::STATUS_NO_RECORD|PPF_Record::STATUS_INVALID
 */
class PPF_Record {
	public const STATUS_OK        = 'ok';
	public const STATUS_NO_RECORD = 'no_record';
	public const STATUS_INVALID   = 'invalid';

	/**
	 * Resolver dependency.
	 *
	 * @var PPF_Record_Resolver
	 */
	private $resolver;

	/**
	 * Resolver error code.
	 *
	 * @var int
	 */
	private $resolver_error = PPF_Record_Resolver::ERROR_NONE;

	/**
	 * Record string.
	 *
	 * @var string|null
	 */
	private $record = null;

	/**
	 * Tokens array.
	 *
	 * @var string[]|null
	 */
	private $tokens = null;

	/**
	 * Create record helper.
	 *
	 * @param PPF_Record_Resolver|null $resolver Resolver dependency.
	 */
	public function __construct( ?PPF_Record_Resolver $resolver = null ) {
		/**
		 * Filters the resolver used for PPF authorization.
		 *
		 * @param PPF_Record_Resolver|null $resolver Resolver instance.
		 */
		$resolver = apply_filters( 'ppf_authorization_resolver', $resolver );

		if ( ! $resolver instanceof PPF_Record_Resolver ) {
			$resolver = new PPF_Record_Default_Resolver();
		}

		$this->resolver = $resolver;
	}

	/**
	 * Resolve IPs for a hostname.
	 *
	 * @param string $host Hostname to query.
	 * @return string[]|false|null Host IPs, false if too many lookups, null on resolver error.
	 */
	public function get_host_ips( $host ) {
		return $this->resolver->get_host_ips( $host );
	}

	/**
	 * Resolve a host's PPF record and tokens.
	 *
	 * @param string $host Hostname.
	 * @return ResolveResult
	 */
	public function resolve( $host ) {
		$host = $this->normalize_host( $host );
		if ( '' === $host ) {
			return self::STATUS_INVALID;
		}

		$lookup = '_pingback.' . $host;
		$txt    = $this->resolver->get_txt_records( $lookup, $this->resolver_error );
		if ( false === $txt ) {
			return self::STATUS_INVALID;
		}
		if ( null === $txt ) {
			return self::STATUS_NO_RECORD;
		}

		$txt    = strtolower( $txt );
		$tokens = explode( ' ', $txt );
		$tokens = array_map( 'trim', $tokens );
		$tokens = array_filter( $tokens );
		if ( 'v=ppf1' === $tokens[0] ) {
			$this->record = $txt;
			$this->tokens = array_slice( $tokens, 1 );
			return self::STATUS_OK;
		}

		return self::STATUS_INVALID;
	}

	/**
	 * Get the resolver instance.
	 *
	 * @return PPF_Record_Resolver
	 */
	public function get_resolver() {
		return $this->resolver;
	}

	/**
	 * Get the resolver error code.
	 *
	 * @return int
	 */
	public function get_resolver_error() {
		return $this->resolver_error;
	}

	/**
	 * Get the tokens array.
	 *
	 * @return string[]
	 */
	public function get_tokens() {
		return $this->tokens ?? array();
	}

	/**
	 * Get the raw record string.
	 *
	 * @return string|null
	 */
	public function get_record() {
		return $this->record;
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
