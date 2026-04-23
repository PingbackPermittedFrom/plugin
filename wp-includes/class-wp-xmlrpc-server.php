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

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/ppf/class-ppf-checker.php';
// @codeCoverageIgnoreEnd

/**
 * Swap the XML-RPC server class for the PPF implementation.
 * 
 * There are no hooks where we need them in the original class.
 * 
 * phpcs:disable -- This is a copy/paste of the original class with the PPF implementation added. Enabled for new code.
 *
 * @codeCoverageIgnore
 */
class wp_xmlrpc_server extends \wp_xmlrpc_server {
	/**
	 * Retrieves a pingback and registers it.
	 *
	 * This is a copy/paste of the original method with the PPF implementation added.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param array $args {
	 *     Method arguments. Note: arguments must be ordered as documented.
	 *
	 *     @type string $0 URL of page linked from.
	 *     @type string $1 URL of page linked to.
	 * }
	 * @return string|IXR_Error
	 */
	public function pingback_ping( $args ) {
		global $wpdb;

		/** This action is documented in wp-includes/class-wp-xmlrpc-server.php */
		do_action( 'xmlrpc_call', 'pingback.ping', $args, $this );

		$this->escape( $args );

		$pagelinkedfrom = str_replace( '&amp;', '&', $args[0] );
		$pagelinkedto   = str_replace( '&amp;', '&', $args[1] );
		$pagelinkedto   = str_replace( '&', '&amp;', $pagelinkedto );

		/**
		 * Filters the pingback source URI.
		 *
		 * @since 3.6.0
		 *
		 * @param string $pagelinkedfrom URI of the page linked from.
		 * @param string $pagelinkedto   URI of the page linked to.
		 */
		$pagelinkedfrom = apply_filters( 'pingback_ping_source_uri', $pagelinkedfrom, $pagelinkedto );

		if ( ! $pagelinkedfrom ) {
			return $this->pingback_error( 0, __( 'A valid URL was not provided.' ) );
		}

		// Check if the page linked to is on our site.
		$pos1 = strpos( $pagelinkedto, str_replace( array( 'http://www.', 'http://', 'https://www.', 'https://' ), '', get_option( 'home' ) ) );
		if ( ! $pos1 ) {
			return $this->pingback_error( 0, __( 'Is there no link to us?' ) );
		}

		/*
		 * Let's find which post is linked to.
		 * FIXME: Does url_to_postid() cover all these cases already?
		 * If so, then let's use it and drop the old code.
		 */
		$urltest = parse_url( $pagelinkedto );
		$post_id = url_to_postid( $pagelinkedto );

		if ( $post_id ) {
			// $way
		} elseif ( isset( $urltest['path'] ) && preg_match( '#p/[0-9]{1,}#', $urltest['path'], $match ) ) {
			// The path defines the post_ID (archives/p/XXXX).
			$blah    = explode( '/', $match[0] );
			$post_id = (int) $blah[1];
		} elseif ( isset( $urltest['query'] ) && preg_match( '#p=[0-9]{1,}#', $urltest['query'], $match ) ) {
			// The query string defines the post_ID (?p=XXXX).
			$blah    = explode( '=', $match[0] );
			$post_id = (int) $blah[1];
		} elseif ( isset( $urltest['fragment'] ) ) {
			// An #anchor is there, it's either...
			if ( (int) $urltest['fragment'] ) {
				// ...an integer #XXXX (simplest case),
				$post_id = (int) $urltest['fragment'];
			} elseif ( preg_match( '/post-[0-9]+/', $urltest['fragment'] ) ) {
				// ...a post ID in the form 'post-###',
				$post_id = preg_replace( '/[^0-9]+/', '', $urltest['fragment'] );
			} elseif ( is_string( $urltest['fragment'] ) ) {
				// ...or a string #title, a little more complicated.
				$title   = preg_replace( '/[^a-z0-9]/i', '.', $urltest['fragment'] );
				$sql     = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title RLIKE %s", $title );
				$post_id = $wpdb->get_var( $sql );
				if ( ! $post_id ) {
					// Returning unknown error '0' is better than die()'ing.
					return $this->pingback_error( 0, '' );
				}
			}
		} else {
			// TODO: Attempt to extract a post ID from the given URL.
			return $this->pingback_error( 33, __( 'The specified target URL cannot be used as a target. It either does not exist, or it is not a pingback-enabled resource.' ) );
		}

		$post_id = (int) $post_id;
		$post    = get_post( $post_id );

		if ( ! $post ) { // Post not found.
			return $this->pingback_error( 33, __( 'The specified target URL cannot be used as a target. It either does not exist, or it is not a pingback-enabled resource.' ) );
		}

		if ( url_to_postid( $pagelinkedfrom ) === $post_id ) {
			return $this->pingback_error( 0, __( 'The source URL and the target URL cannot both point to the same resource.' ) );
		}

		// Check if pings are on.
		if ( ! pings_open( $post ) ) {
			return $this->pingback_error( 33, __( 'The specified target URL cannot be used as a target. It either does not exist, or it is not a pingback-enabled resource.' ) );
		}

		// Let's check that the remote site didn't already pingback this entry.
		if ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_author_url = %s", $post_id, $pagelinkedfrom ) ) ) {
			return $this->pingback_error( 48, __( 'The pingback has already been registered.' ) );
		}

		/**
		 * Check Pingback Permitted From.
		 *
		 * @see https://spec.ppf1.org/
		 * 
		 * phpcs:enable
		 */
		$ppf_checker = new PPF_Checker();
		$remote_ip   = $ppf_checker->check( $pagelinkedfrom, $pagelinkedto, $args );
		if ( is_wp_error( $remote_ip ) ) {
			return $this->pingback_error( $remote_ip->get_error_code(), $remote_ip->get_error_message() );
		} elseif ( false === $remote_ip ) {
			$remote_ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] ); // phpcs:ignore -- From the original class.
		}

		/* Add PPF result to comment meta */
		$comment_meta = array(
			'ppf_result' => $ppf_checker->get_result(),
		);
		// phpcs:disable

		/** This filter is documented in wp-includes/class-wp-http.php */
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ), $pagelinkedfrom );

		// Let's check the remote site.
		$http_api_args = array(
			'timeout'             => 10,
			'redirection'         => 0,
			'limit_response_size' => 153600, // 150 KB
			'user-agent'          => "$user_agent; verifying pingback from $remote_ip",
			'headers'             => array(
				'X-Pingback-Forwarded-For' => $remote_ip,
			),
		);

		// phpcs:enable
		/**
		 * Pre wp_safe_remote_get action.
		 * 
		 * @param array  $http_api_args HTTP API arguments.
		 * @param string $pagelinkedfrom URL of the page linked from.
		 */
		$http_api_args = apply_filters( 'pingback_pre_remote_get', $http_api_args, $pagelinkedfrom );
		// phpcs:disable

		$request                = wp_safe_remote_get( $pagelinkedfrom, $http_api_args );
		$remote_source          = wp_remote_retrieve_body( $request );
		$remote_source_original = $remote_source;

		if ( ! $remote_source ) {
			return $this->pingback_error( 16, __( 'The source URL does not exist.' ) );
		}

		/**
		 * Filters the pingback remote source.
		 *
		 * @since 2.5.0
		 *
		 * @param string $remote_source Response source for the page linked from.
		 * @param string $pagelinkedto  URL of the page linked to.
		 */
		$remote_source = apply_filters( 'pre_remote_source', $remote_source, $pagelinkedto );

		// Work around bug in strip_tags():
		$remote_source = str_replace( '<!DOC', '<DOC', $remote_source );
		$remote_source = preg_replace( '/[\r\n\t ]+/', ' ', $remote_source ); // normalize spaces
		$remote_source = preg_replace( '/<\/*(h1|h2|h3|h4|h5|h6|p|th|td|li|dt|dd|pre|caption|input|textarea|button|body)[^>]*>/', "\n\n", $remote_source );

		preg_match( '|<title>([^<]*?)</title>|is', $remote_source, $matchtitle );
		$title = isset( $matchtitle[1] ) ? $matchtitle[1] : '';
		if ( empty( $title ) ) {
			return $this->pingback_error( 32, __( 'A title on that page cannot be found.' ) );
		}

		// Remove all script and style tags including their content.
		$remote_source = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $remote_source );
		// Just keep the tag we need.
		$remote_source = strip_tags( $remote_source, '<a>' );

		$p = explode( "\n\n", $remote_source );

		$preg_target = preg_quote( $pagelinkedto, '|' );

		foreach ( $p as $para ) {
			if ( str_contains( $para, $pagelinkedto ) ) { // It exists, but is it a link?
				preg_match( '|<a[^>]+?' . $preg_target . '[^>]*>([^>]+?)</a>|', $para, $context );

				// If the URL isn't in a link context, keep looking.
				if ( empty( $context ) ) {
					continue;
				}

				/*
				 * We're going to use this fake tag to mark the context in a bit.
				 * The marker is needed in case the link text appears more than once in the paragraph.
				 */
				$excerpt = preg_replace( '|\</?wpcontext\>|', '', $para );

				// prevent really long link text
				if ( strlen( $context[1] ) > 100 ) {
					$context[1] = substr( $context[1], 0, 100 ) . '&#8230;';
				}

				$marker      = '<wpcontext>' . $context[1] . '</wpcontext>';  // Set up our marker.
				$excerpt     = str_replace( $context[0], $marker, $excerpt ); // Swap out the link for our marker.
				$excerpt     = strip_tags( $excerpt, '<wpcontext>' );         // Strip all tags but our context marker.
				$excerpt     = trim( $excerpt );
				$preg_marker = preg_quote( $marker, '|' );
				$excerpt     = preg_replace( "|.*?\s(.{0,100}$preg_marker.{0,100})\s.*|s", '$1', $excerpt );
				$excerpt     = strip_tags( $excerpt ); // YES, again, to remove the marker wrapper.
				break;
			}
		}

		if ( empty( $context ) ) { // Link to target not found.
			return $this->pingback_error( 17, __( 'The source URL does not contain a link to the target URL, and so cannot be used as a source.' ) );
		}

		$pagelinkedfrom = str_replace( '&', '&amp;', $pagelinkedfrom );

		$context        = '[&#8230;] ' . esc_html( $excerpt ) . ' [&#8230;]';
		$pagelinkedfrom = $this->escape( $pagelinkedfrom );

		$comment_post_id      = (int) $post_id;
		$comment_author       = $title;
		$comment_author_email = '';
		$this->escape( $comment_author );
		$comment_author_url = $pagelinkedfrom;
		$comment_content    = $context;
		$this->escape( $comment_content );
		$comment_type = 'pingback';

		$commentdata = array(
			'comment_post_ID' => $comment_post_id,
		);

		$commentdata += compact(
			'comment_author',
			'comment_author_url',
			'comment_author_email',
			'comment_content',
			'comment_type',
			'remote_source',
			'remote_source_original'
		);

		$comment_id = wp_new_comment( $commentdata );

		if ( is_wp_error( $comment_id ) ) {
			return $this->pingback_error( 0, $comment_id->get_error_message() );
		}

		/**
		 * Filter the pingback comment meta.
		 * 
		 * @param array  $comment_meta Comment meta.
		 * @param int    $comment_id   Comment ID.
		 * 
		 * phpcs:enable
		 */
		$comment_meta = apply_filters( 'pingback_pre_comment_meta', $comment_meta, $comment_id );

		foreach ( $comment_meta as $key => $value ) {
			add_comment_meta( $comment_id, $key, $value, true );
		}
		// phpcs:disable

		/**
		 * Fires after a post pingback has been sent.
		 *
		 * @since 0.71
		 *
		 * @param int $comment_id Comment ID.
		 */
		do_action( 'pingback_post', $comment_id );

		/* translators: 1: URL of the page linked from, 2: URL of the page linked to. */
		return sprintf( __( 'Pingback from %1$s to %2$s registered. Keep the web talking! :-)' ), $pagelinkedfrom, $pagelinkedto );
	}
}
