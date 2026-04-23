<?php
/**
 * Pingback Permitted From (PPF)
 *
 * Handle PPF for Trackbacks.
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
 * Pre-process the trackback post.
 *
 * @param int    $post_id       Post ID related to the trackback.
 * @param string $trackback_url Trackback URL.
 * @param string $charset       Character set.
 * @param string $title         Trackback title.
 * @param string $excerpt       Trackback excerpt.
 * @param string $blog_name     Site name.
 * @return void
 *
 * @codeCoverageIgnore
 */
function ppf_pre_trackback_post( // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Filter callback; signature required.
	int $post_id,
	string $trackback_url,
	string $charset,
	string $title,
	string $excerpt,
	string $blog_name
) {
	require_once __DIR__ . '/wp-includes/ppf/class-ppf-checker.php';

	global $ppf_checker;

	$ppf_checker = new PPF_Checker();
	$remote_ip   = $ppf_checker->check( $trackback_url, (string) $post_id );
	if ( is_wp_error( $remote_ip ) ) {
		\trackback_response( 1, $remote_ip->get_error_message() );
	}
}
add_action( 'pre_trackback_post', __NAMESPACE__ . '\\ppf_pre_trackback_post', 10, 6 );

/**
 * Process the trackback post.
 *
 * Add the PPF result to the trackback comment meta.
 *
 * @param int $trackback_id Trackback ID.
 * @return void
 *
 * @codeCoverageIgnore
 */
function ppf_trackback_post( $trackback_id ) {
	global $ppf_checker;

	add_comment_meta( $trackback_id, 'ppf_result', $ppf_checker->get_result(), true );
}
add_action( 'trackback_post', __NAMESPACE__ . '\\ppf_trackback_post' );
