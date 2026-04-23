<?php
/**
 * Pingback Permitted From (PPF)
 *
 * Logger for PPF debugging.
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

/**
 * Development-only logger for PPF prototype.
 *
 * @codeCoverageIgnore
 */
class PPF_Logger {
	/**
	 * Log a message when debug verbosity is enabled.
	 *
	 * Development-only logging for this prototype; remove for production use.
	 *
	 * @param string       $message Message to log.
	 * @param array<mixed> $context Optional context.
	 * @return void
	 */
	public static function log_message( $message, $context = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		if ( ! defined( 'PPF_DEBUG' ) || ! PPF_DEBUG ) {
			return;
		}

		$payload = $message;
		if ( ! empty( $context ) ) {
			$payload .= ' ' . wp_json_encode( $context );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Development-only logger; see class docblock.
		error_log( '[PPF] ' . $payload );
	}
}
