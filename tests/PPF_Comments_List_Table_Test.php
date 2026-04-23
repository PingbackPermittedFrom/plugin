<?php
/**
 * Tests for the PPF comments list table helper.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PPF\Pingback\PPF_Result;
use PPF\Pingback\WP_Comments_List_Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

require_once dirname( __DIR__ ) . '/wp-admin/includes/class-wp-comments-list-table.php';

/**
 * Coverage for the comments list table helper.
 *
 * @group ppf-comments-list-table
 */
#[CoversClass( WP_Comments_List_Table::class )]
class PPF_Comments_List_Table_Test extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Ensure the comments list table registers its hooks in the constructor.
	 *
	 * @return void
	 */
	public function test_constructor_registers_hooks() {
		$table = new WP_Comments_List_Table();

		$this->assertSame( 10, \has_filter( 'manage_edit-comments_columns', array( $table, 'ppf_manage_comments_columns' ) ) );
		$this->assertSame( 10, \has_action( 'manage_comments_custom_column', array( $table, 'ppf_manage_comments_custom_column' ) ) );
	}

	/**
	 * Ensure the comments list table adds the PPF column.
	 *
	 * @return void
	 */
	public function test_adds_ppf_column() {
		$table = new WP_Comments_List_Table();

		Functions\when( '__' )->returnArg();

		$result = $table->ppf_manage_comments_columns( array( 'author' => 'Author' ) );

		$this->assertSame( 'PPF', $result['ppf_result'] );
	}

	/**
	 * Provide result branches for comment icon rendering.
	 *
	 * @return array<string, array{0:string,1:string,2:string}>
	 */
	public static function pingback_result_provider() {
		return array(
			'match'      => array( PPF_Result::MATCH, 'dashicons-yes', 'Match' ),
			'no_match'   => array( PPF_Result::NO_MATCH, 'dashicons-no', 'No match' ),
			'no_record'  => array( PPF_Result::NO_RECORD, 'dashicons-no-alt', 'No record' ),
			'invalid'    => array( PPF_Result::INVALID, 'dashicons-warning', 'Invalid record' ),
			'error'      => array( PPF_Result::ERROR, 'dashicons-warning', 'Error' ),
			'unknown'    => array( PPF_Result::UNKNOWN, 'dashicons-warning', 'Unknown result' ),
			'unexpected' => array( 'surprise', 'dashicons-marker', 'Unknown result' ),
		);
	}

	/**
	 * Ensure a pingback row renders the expected icon for each result branch.
	 *
	 * @param string $result Stored result value.
	 * @param string $icon Expected icon class.
	 * @param string $title Expected title text.
	 * @return void
	 */
	#[DataProvider( 'pingback_result_provider' )]
	public function test_renders_expected_icon_for_pingback_result( $result, $icon, $title ) {
		$table = new WP_Comments_List_Table();

		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\expect( 'PPF\\Pingback\\get_comment' )
			->once()
			->with( 7 )
			->andReturn(
				(object) array(
					'comment_type' => 'pingback',
				)
			);
		Functions\expect( 'PPF\\Pingback\\get_comment_meta' )
			->once()
			->with( 7, 'ppf_result', true )
			->andReturn( $result );

		ob_start();
		$table->ppf_manage_comments_custom_column( 'ppf_result', 7 );
		$output = ob_get_clean();

		$this->assertIsString( $output );
		$this->assertStringContainsString( $icon, $output );
		$this->assertStringContainsString( $title, $output );
	}

	/**
	 * Ensure non-pingback comments render the generic marker icon.
	 *
	 * @return void
	 */
	public function test_renders_generic_marker_for_non_pingback_comment() {
		$table = new WP_Comments_List_Table();

		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\expect( 'PPF\\Pingback\\get_comment' )
			->once()
			->with( 9 )
			->andReturn(
				(object) array(
					'comment_type' => 'comment',
				)
			);

		ob_start();
		$table->ppf_manage_comments_custom_column( 'ppf_result', 9 );
		$output = ob_get_clean();

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'dashicons-marker', $output );
		$this->assertStringContainsString( 'Not a pingback', $output );
	}

	/**
	 * Ensure non-PPF columns are ignored.
	 *
	 * @return void
	 */
	public function test_ignores_non_ppf_column() {
		$table = new WP_Comments_List_Table();

		ob_start();
		$table->ppf_manage_comments_custom_column( 'author', 7 );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Ensure missing comments produce no output.
	 *
	 * @return void
	 */
	public function test_returns_no_output_when_comment_is_missing() {
		$table = new WP_Comments_List_Table();

		Functions\expect( 'PPF\\Pingback\\get_comment' )
			->once()
			->with( 7 )
			->andReturn( false );

		ob_start();
		$table->ppf_manage_comments_custom_column( 'ppf_result', 7 );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}
}
