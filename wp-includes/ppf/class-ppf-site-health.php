<?php
/**
 * Pingback Permitted From (PPF)
 *
 * Site Health tests for PPF (Pingback Permitted From) DNS records.
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

require_once __DIR__ . '/class-ppf-record-resolver.php';
require_once __DIR__ . '/class-ppf-record.php';

/**
 * Registers and runs Site Health tests that check for PPF records based on the site address.
 */
class PPF_Site_Health {

	/**
	 * Test identifier for the direct PPF record check.
	 *
	 * @var string
	 */
	const TEST_PPF_RECORD = 'ppf_record';

	/**
	 * Test identifier for trusted proxy configuration.
	 *
	 * @var string
	 */
	const TEST_PPF_TRUSTED_PROXIES = 'ppf_trusted_proxies';

	/**
	 * Test identifier for XML-RPC server ownership.
	 *
	 * @var string
	 */
	const TEST_PPF_XMLRPC_SERVER = 'ppf_xmlrpc_server';

	/**
	 * Registers the PPF site health tests with WordPress.
	 *
	 * Hook: site_status_tests
	 *
	 * @param array<string, array<string, array<string, mixed>>> $tests Existing direct and async tests.
	 * @return array<string, array<string, array<string, mixed>>> Modified tests array.
	 */
	public static function add_tests( $tests ) {
		$tests['direct'][ self::TEST_PPF_RECORD ]          = array(
			'label' => __( 'Pingback Permitted From (PPF) record', 'pingback-permitted-from' ),
			'test'  => array( __CLASS__, 'run_ppf_record_test' ),
		);
		$tests['direct'][ self::TEST_PPF_TRUSTED_PROXIES ] = array(
			'label' => __( 'Pingback Permitted From (PPF) trusted proxies', 'pingback-permitted-from' ),
			'test'  => array( __CLASS__, 'run_ppf_trusted_proxies_test' ),
		);
		$tests['direct'][ self::TEST_PPF_XMLRPC_SERVER ]   = array(
			'label' => __( 'Pingback Permitted From (PPF) XML-RPC server', 'pingback-permitted-from' ),
			'test'  => array( __CLASS__, 'run_ppf_xmlrpc_server_test' ),
		);

		return $tests;
	}

	/**
	 * Runs the PPF record check for the site's host.
	 *
	 * Looks up the site address (home URL), resolves the host, and checks for a valid
	 * _pingback.<host> TXT record with v=ppf1.
	 *
	 * @return array<string, mixed> Site Health test result (label, status, badge, description, actions, test).
	 */
	public static function run_ppf_record_test() {
		$home   = home_url();
		$host   = $home ? wp_parse_url( $home, PHP_URL_HOST ) : '';
		$record = new PPF_Record();

		$result = array(
			'label'       => '',
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'pingback-permitted-from' ),
				'color' => 'blue',
			),
			'description' => '',
			'actions'     => '',
			'test'        => self::TEST_PPF_RECORD,
		);

		if ( empty( $host ) ) {
			$result['status']      = 'critical';
			$result['label']       = __( 'Site address could not be determined for PPF check', 'pingback-permitted-from' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'The PPF (Pingback Permitted From) check requires a valid site address. Verify Settings → General → Site Address.', 'pingback-permitted-from' )
			);
			$result['actions']     = sprintf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( admin_url( 'options-general.php#home' ) ),
				__( 'Review Site Address', 'pingback-permitted-from' )
			);
			return $result;
		}

		$status = $record->resolve( $host );

		switch ( $status ) {
			case PPF_Record::STATUS_OK:
				$tokens                = $record->get_tokens();
				$result['label']       = __( 'A valid PPF record is published for this site', 'pingback-permitted-from' );
				$result['description'] = sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: 1: hostname, 2: comma-separated tokens or "none" */
						__( 'The host <code>%1$s</code> has a valid Pingback Permitted From (PPF) TXT record at <code>_pingback.%1$s</code>. Authorized tokens: %2$s.', 'pingback-permitted-from' ),
						esc_html( $host ),
						$tokens ? esc_html( implode( ', ', $tokens ) ) : _x( 'none', 'PPF tokens', 'pingback-permitted-from' )
					)
				);
				break;

			case PPF_Record::STATUS_NO_RECORD:
				$result['status']      = 'recommended';
				$result['label']       = __( 'No PPF record found for this site', 'pingback-permitted-from' );
				$result['description'] = sprintf(
					'<p>%s</p><p>%s</p>',
					sprintf(
						/* translators: %s: hostname */
						__( 'No <em>Pingback Permitted From</em> (PPF) TXT record was found for <code>%s</code>.', 'pingback-permitted-from' ),
						esc_html( $host )
					),
					__( 'Adding a PPF record at <code>_pingback.&lt;your-domain&gt;</code> lets you authorize which sources can send pingbacks to this site via DNS.', 'pingback-permitted-from' )
				);
				$result['actions'] = sprintf(
					'<p><a href="%1$s" target="_blank" rel="noopener">%2$s<span class="screen-reader-text"> %3$s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
					esc_url( 'https://ppf1.org/wordpress/' ),
					__( 'Learn about PPF records', 'pingback-permitted-from' ),
					/* translators: Hidden accessibility text. */
					__( '(opens in a new tab)', 'pingback-permitted-from' )
				);
				break;

			case PPF_Record::STATUS_INVALID:
			default:
				$resolver_error   = $record->get_resolver_error();
				$result['status'] = 'critical';
				$result['label']  = __( 'Invalid or conflicting PPF record for this site', 'pingback-permitted-from' );
				if ( PPF_Record_Resolver::ERROR_TOO_MANY_LOOKUPS === $resolver_error ) {
					$result['description'] = sprintf(
						'<p>%s</p><p>%s</p>',
						sprintf(
							/* translators: %s: hostname */
							__( 'The DNS lookup depth for <code>_pingback.%s</code> exceeded the resolver safety limit while resolving the PPF record.', 'pingback-permitted-from' ),
							esc_html( $host )
						),
						__( 'Simplify your DNS chain for the PPF TXT record (for example, reduce nested CNAME-like indirections) so the record can be resolved within limits.', 'pingback-permitted-from' )
					);
				} elseif ( PPF_Record_Resolver::ERROR_TOO_MANY_RECORDS === $resolver_error ) {
					$result['description'] = sprintf(
						'<p>%s</p><p>%s</p>',
						sprintf(
							/* translators: %s: hostname */
							__( 'Multiple TXT records were returned for <code>_pingback.%s</code>, but PPF requires exactly one authoritative TXT value.', 'pingback-permitted-from' ),
							esc_html( $host )
						),
						__( 'Consolidate the PPF policy into a single TXT record to avoid ambiguity.', 'pingback-permitted-from' )
					);
				} else {
					$result['description'] = sprintf(
						'<p>%s</p><p>%s</p>',
						sprintf(
							/* translators: %s: hostname */
							__( 'A TXT record exists at <code>_pingback.%s</code> but it is not a valid PPF record (expected <code>v=ppf1</code> and optional tokens).', 'pingback-permitted-from' ),
							esc_html( $host )
						),
						__( 'Correct or remove the record so that PPF authorization can work as intended.', 'pingback-permitted-from' )
					);
				}
				break;
		}

		return $result;
	}

	/**
	 * Runs the trusted proxy configuration check for PPF.
	 *
	 * @return array<string, mixed> Site Health test result (label, status, badge, description, actions, test).
	 */
	public static function run_ppf_trusted_proxies_test() {
		$has_forwarded_for_header = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && is_string( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && '' !== $_SERVER['HTTP_X_FORWARDED_FOR'];

		$result = array(
			'label'       => '',
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'pingback-permitted-from' ),
				'color' => 'blue',
			),
			'description' => '',
			'actions'     => '',
			'test'        => self::TEST_PPF_TRUSTED_PROXIES,
		);

		if ( ! defined( 'PPF_TRUSTED_PROXIES' ) ) {
			if ( $has_forwarded_for_header ) {
				$result['status']      = 'critical';
				$result['label']       = __( 'PPF trusted proxies are not configured', 'pingback-permitted-from' );
				$result['description'] = sprintf(
					'<p>%s</p>',
					__( 'This request includes <code>X-Forwarded-For</code>, but <code>PPF_TRUSTED_PROXIES</code> is not defined. Configure it in <code>wp-config.php</code> as an array of trusted proxy CIDR ranges or IP addresses so PPF can safely honor forwarded client IPs.', 'pingback-permitted-from' )
				);
				return $result;
			}

			$result['label']       = __( 'PPF trusted proxies are not configured', 'pingback-permitted-from' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'No <code>X-Forwarded-For</code> header is present on this request, so PPF will use the direct peer IP address.', 'pingback-permitted-from' )
			);

			return $result;
		}

		$trusted_proxies = constant( 'PPF_TRUSTED_PROXIES' );
		if ( ! is_array( $trusted_proxies ) ) {
			if ( $has_forwarded_for_header ) {
				$result['status']      = 'critical';
				$result['label']       = __( 'PPF trusted proxies configuration is invalid', 'pingback-permitted-from' );
				$result['description'] = sprintf(
					'<p>%s</p>',
					__( 'This request includes <code>X-Forwarded-For</code>, but <code>PPF_TRUSTED_PROXIES</code> is not a valid array. Update the constant in <code>wp-config.php</code> to use an array of CIDR ranges or IP addresses.', 'pingback-permitted-from' )
				);
				return $result;
			}

			$result['label']       = __( 'PPF trusted proxies configuration is invalid', 'pingback-permitted-from' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'No <code>X-Forwarded-For</code> header is present on this request, so the invalid <code>PPF_TRUSTED_PROXIES</code> value is not currently affecting PPF authorization.', 'pingback-permitted-from' )
			);

			return $result;
		}

		if ( array() === $trusted_proxies ) {
			$result['label']       = __( 'PPF trusted proxies are explicitly disabled', 'pingback-permitted-from' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( '<code>PPF_TRUSTED_PROXIES</code> is set to an empty array, so PPF will always use the direct peer IP and ignore forwarded client IP headers.', 'pingback-permitted-from' )
			);

			return $result;
		}

			$proxy_count       = count( array_filter( $trusted_proxies, 'is_string' ) );
		$result['label']       = __( 'PPF trusted proxies are configured', 'pingback-permitted-from' );
		$result['description'] = sprintf(
			'<p>%s</p>',
			sprintf(
				/* translators: %d: number of configured trusted proxies. */
				_n(
					'<code>PPF_TRUSTED_PROXIES</code> contains %d configured proxy entry. PPF will honor forwarded client IP headers only from those direct peers.',
					'<code>PPF_TRUSTED_PROXIES</code> contains %d configured proxy entries. PPF will honor forwarded client IP headers only from those direct peers.',
					$proxy_count,
					'pingback-permitted-from'
				),
				$proxy_count
			)
		);

		return $result;
	}

	/**
	 * Runs the XML-RPC server ownership check for PPF.
	 *
	 * @return array<string, mixed> Site Health test result (label, status, badge, description, actions, test).
	 */
	public static function run_ppf_xmlrpc_server_test() {
		$ppf_class    = __NAMESPACE__ . '\\wp_xmlrpc_server';
		$server_class = apply_filters( 'wp_xmlrpc_server_class', 'wp_xmlrpc_server' );
		$result       = array(
			'label'       => '',
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'pingback-permitted-from' ),
				'color' => 'blue',
			),
			'description' => '',
			'actions'     => '',
			'test'        => self::TEST_PPF_XMLRPC_SERVER,
		);

		if ( $ppf_class === $server_class ) {
			$result['label']       = __( 'PPF XML-RPC server is active', 'pingback-permitted-from' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'Pingback requests are currently being handled by the PPF XML-RPC server override.', 'pingback-permitted-from' )
			);

			return $result;
		}

		$result['status'] = 'critical';

		if ( 'wp_xmlrpc_server' === $server_class ) {
			$result['label']       = __( 'PPF XML-RPC server is not active', 'pingback-permitted-from' );
			$result['description'] = sprintf(
				'<p>%s</p>',
				__( 'The active XML-RPC server class is still the WordPress core default, so the PPF XML-RPC override is not currently in effect.', 'pingback-permitted-from' )
			);

			return $result;
		}

		$conflict = self::get_xmlrpc_server_conflict_source();
		if ( is_array( $conflict ) ) {
			$result['label']           = __( 'Another XML-RPC server override is active', 'pingback-permitted-from' );
				$result['description'] = sprintf(
					'<p>%s</p><p>%s</p>',
					sprintf(
						/* translators: %s: active XML-RPC server class name. */
						__( 'The active XML-RPC server class is <code>%s</code>, so the PPF override is not currently handling pingbacks.', 'pingback-permitted-from' ),
						esc_html( $server_class )
					),
					sprintf(
						/* translators: 1: callback label, 2: callback file path. */
						__( 'The conflicting override appears to come from <code>%1$s</code>%2$s.', 'pingback-permitted-from' ),
						esc_html( $conflict['label'] ),
						$conflict['file']
							? sprintf(
								/* translators: %s: callback file path. */
								__( ' in <code>%s</code>', 'pingback-permitted-from' ),
								esc_html( $conflict['file'] )
							)
							: ''
					)
				);

			return $result;
		}

		$result['label']       = __( 'Another XML-RPC server override is active', 'pingback-permitted-from' );
		$result['description'] = sprintf(
			'<p>%s</p>',
			sprintf(
				/* translators: %s: active XML-RPC server class name. */
				__( 'The active XML-RPC server class is <code>%s</code>, so the PPF override is not currently handling pingbacks.', 'pingback-permitted-from' ),
				esc_html( $server_class )
			)
		);

		return $result;
	}

	/**
	 * Locate the callback most likely responsible for replacing the XML-RPC server class.
	 *
	 * @return array{label:string,file:string|null}|null
	 */
	private static function get_xmlrpc_server_conflict_source() {
		global $wp_filter;

		if ( ! isset( $wp_filter['wp_xmlrpc_server_class'] ) ) {
			return null;
		}

		$hook = $wp_filter['wp_xmlrpc_server_class'];
		if ( is_object( $hook ) && isset( $hook->callbacks ) ) {
			$callbacks = $hook->callbacks;
		} elseif ( is_array( $hook ) && isset( $hook['callbacks'] ) ) {
			$callbacks = $hook['callbacks'];
		} else {
			return null;
		}

		if ( ! is_array( $callbacks ) ) {
			return null;
		}

		$last_external = null;
		foreach ( $callbacks as $priority_callbacks ) {
			if ( ! is_array( $priority_callbacks ) ) {
				continue;
			}

			foreach ( $priority_callbacks as $callback_data ) {
				if ( ! is_array( $callback_data ) || ! isset( $callback_data['function'] ) ) {
					continue;
				}

				$callback = $callback_data['function'];
				if ( self::is_ppf_xmlrpc_callback( $callback ) ) {
					return $last_external;
				}

				$last_external = self::describe_callback( $callback );
			}
		}

		return $last_external;
	}

	/**
	 * Determine whether a callback is the PPF XML-RPC class swap.
	 *
	 * @param mixed $callback Hook callback.
	 * @return bool
	 */
	private static function is_ppf_xmlrpc_callback( $callback ) {
		return 'PPF\\Pingback\\ppf_xmlrpc_server_class' === $callback;
	}

	/**
	 * Describe a callback and attempt to resolve its file path.
	 *
	 * @param mixed $callback Hook callback.
	 * @return array{label:string,file:string|null}
	 */
	protected static function describe_callback( $callback ) {
		$label = self::format_callback_label( $callback );
		$file  = null;

		try {
			if ( is_string( $callback ) && function_exists( $callback ) ) {
				$reflection = new \ReflectionFunction( $callback );
				$file_name  = $reflection->getFileName();
				$file       = false !== $file_name ? $file_name : null;
			} elseif (
				is_array( $callback ) &&
				isset( $callback[0], $callback[1] ) &&
				( is_object( $callback[0] ) || is_string( $callback[0] ) ) &&
				is_string( $callback[1] )
			) {
				$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
				$file_name  = $reflection->getFileName();
				$file       = false !== $file_name ? $file_name : null;
			} elseif ( $callback instanceof \Closure ) {
				$reflection = new \ReflectionFunction( $callback );
				$file_name  = $reflection->getFileName();
				$file       = false !== $file_name ? $file_name : null;
			}
		} catch ( \ReflectionException $e ) {
			$file = null;
		}

		return array(
			'label' => $label,
			'file'  => $file,
		);
	}

	/**
	 * Format a callback for display in Site Health output.
	 *
	 * @param mixed $callback Hook callback.
	 * @return string
	 */
	protected static function format_callback_label( $callback ) {
		if ( is_string( $callback ) ) {
			return $callback;
		}

		if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
			if ( is_object( $callback[0] ) ) {
				$target = get_class( $callback[0] );
			} elseif ( is_string( $callback[0] ) ) {
				$target = $callback[0];
			} else {
				$target = 'unknown target';
			}
			/**
			 * Callback process.
			 *
			 * @var string $proc
			 */
			$proc = $callback[1];
			return $target . '::' . $proc;
		}

		if ( $callback instanceof \Closure ) {
			return 'Closure';
		}

		return 'unknown callback';
	}
}
