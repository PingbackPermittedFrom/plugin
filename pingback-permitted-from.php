<?php
/**
 * Pingback Permitted From (PPF)
 *
 * @author  Charles Lecklider
 * @license GPL-2.0+
 * @link    https://ppf1.org/
 * @package pingback-permitted-from
 */

/**
 * Plugin Name:       Pingback Permitted From
 * Description:       Synchronous Direct-mode PPF authorization for XML-RPC pingbacks and trackbacks.
 * Version:           0.9.0
 * Author:            Charles Lecklider
 * Author URI:        https://invis.net/
 * Security:          security@ppf1.org
 * License:           GPL-2.0+
 * Text Domain:       pingback-permitted-from
 * Requires PHP:      7.4
 * Update URI:        PingbackPermittedFrom/plugin
 * GitHub Plugin URI: https://github.com/PingbackPermittedFrom/plugin
 * Primary Branch:    main
 * Release Asset:     true
 * Network:           true
 *
 * @package pingback-permitted-from
 */

namespace PPF\Pingback;

require_once __DIR__ . '/wp-includes/ppf/class-ppf-result.php';


/**
 * Swap the XML-RPC server class for the PPF implementation.
 *
 * Run as late as possible to let any other plugins swap the class name first.
 *
 * @param string $class_name Core class name (unused; filter contract).
 * @return string
 *
 * @codeCoverageIgnore
 */
function ppf_xmlrpc_server_class( $class_name ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Filter callback; signature required.
	if ( 'wp_xmlrpc_server' === $class_name ) {
		if ( defined( 'XMLRPC_REQUEST' ) ) {
			require_once __DIR__ . '/wp-includes/class-wp-xmlrpc-server.php';
		}

		return __NAMESPACE__ . '\\wp_xmlrpc_server';
	}

	return $class_name;
}

add_filter( 'wp_xmlrpc_server_class', __NAMESPACE__ . '\\ppf_xmlrpc_server_class', PHP_INT_MAX );


/**
 * Initialize the PPF plugin.
 *
 * @return void
 *
 * @codeCoverageIgnore
 */
function ppf_init() {
	require_once __DIR__ . '/wp-trackback.php';

	require_once __DIR__ . '/wp-includes/ppf/class-ppf-site-health.php';
	add_filter( 'site_status_tests', array( __NAMESPACE__ . '\\PPF_Site_Health', 'add_tests' ) );
}

add_action( 'init', __NAMESPACE__ . '\\ppf_init' );


/**
 * Initialize the PPF admin functionality.
 *
 * @return void
 *
 * @codeCoverageIgnore
 */
function ppf_admin_init() {
	require_once __DIR__ . '/wp-admin/includes/class-wp-comments-list-table.php';
	new WP_Comments_List_Table();
}

add_action( 'admin_init', __NAMESPACE__ . '\\ppf_admin_init' );
