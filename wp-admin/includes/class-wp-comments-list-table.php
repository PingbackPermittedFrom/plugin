<?php
/**
 * Pingback Permitted From (PPF)
 *
 * PPF column for the Comments list table.
 *
 * Adds a Pingback Permitted From result column via the list table hooks.
 * If merged into core, this file would live in wp-admin/includes/.
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
 * Class for adding a PPF result column to the comments list table.
 *
 * @package pingback-permitted-from
 */
class WP_Comments_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'manage_edit-comments_columns', array( $this, 'ppf_manage_comments_columns' ) );
		add_action( 'manage_comments_custom_column', array( $this, 'ppf_manage_comments_custom_column' ), 10, 2 );
	}

	/**
	 * Add the PPF column to the comments list table.
	 *
	 * @param array<string, string> $columns Comment list table columns.
	 * @return array<string, string>
	 */
	public function ppf_manage_comments_columns( $columns ) {
		$columns['ppf_result'] = __( 'PPF', 'pingback-permitted-from' );
		return $columns;
	}

	/**
	 * Output the PPF result cell for each comment row.
	 *
	 * Fires from WP_Comments_List_Table::column_default() for custom columns.
	 *
	 * @param string $column_name Column name.
	 * @param int    $comment_id  Comment ID.
	 * @return void
	 */
	public function ppf_manage_comments_custom_column( $column_name, $comment_id ) {
		if ( 'ppf_result' !== $column_name ) {
			return;
		}
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return;
		}
		if ( 'pingback' === $comment->comment_type ) {
			$result = get_comment_meta( $comment_id, 'ppf_result', true );
			switch ( $result ) {
				case PPF_Result::MATCH:
					$icon  = 'dashicons-yes';
					$title = __( 'Match', 'pingback-permitted-from' );
					break;
				case PPF_Result::NO_MATCH:
					$icon  = 'dashicons-no';
					$title = __( 'No match', 'pingback-permitted-from' );
					break;
				case PPF_Result::NO_RECORD:
					$icon  = 'dashicons-no-alt';
					$title = __( 'No record', 'pingback-permitted-from' );
					break;
				case PPF_Result::INVALID:
					$icon  = 'dashicons-warning';
					$title = __( 'Invalid record', 'pingback-permitted-from' );
					break;
				case PPF_Result::ERROR:
					$icon  = 'dashicons-warning';
					$title = __( 'Error', 'pingback-permitted-from' );
					break;
				case PPF_Result::UNKNOWN:
					$icon  = 'dashicons-warning';
					$title = __( 'Unknown result', 'pingback-permitted-from' );
					break;
				default:
					$icon  = 'dashicons-marker';
					$title = __( 'Unknown result', 'pingback-permitted-from' );
					break;
			}
		} else {
			$icon  = 'dashicons-marker';
			$title = __( 'Not a pingback', 'pingback-permitted-from' );
		}
		echo '<span class="ppf-result-icon" title="' . esc_attr( $title ) . '"><span class="dashicons ' . esc_attr( $icon ) . '"></span></span>';
	}
}
