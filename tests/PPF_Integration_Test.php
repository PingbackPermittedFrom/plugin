<?php
/**
 * Tests for trackback integration helpers.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PPF\Pingback\PPF_Result;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * Coverage for plugin integration seams.
 *
 * @group ppf-integration
 */
#[CoversNothing]
class PPF_Integration_Test extends TestCase {
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
	 * Enable strict mode.
	 *
	 * @return void
	 */
	protected function enableStrictMode(): void {
		Functions\when( 'defined' )->alias(
			function ( $constant ) {
				if ( 'PPF_STRICT_MODE' === $constant ) {
					return true;
				}
				return _defined( $constant );
			}
		);
		Functions\when( 'constant' )->alias(
			function ( $constant ) {
				if ( 'PPF_STRICT_MODE' === $constant ) {
					return true;
				}
				return _constant( $constant );
			}
		);
	}

	/**
	 * Load the trackback integration file once for this process.
	 *
	 * @return void
	 */
	protected function loadTrackbackFile(): void {
		Functions\when( 'add_filter' )->justReturn( true );
		if ( ! function_exists( 'PPF\\Pingback\\ppf_pre_trackback_post' ) ) {
			require_once dirname( __DIR__ ) . '/wp-trackback.php';
		}
	}

	/**
	 * Ensure trackback pre-processing returns an XML-RPC error response on checker failure.
	 *
	 * @return void
	 */
	public function test_ppf_pre_trackback_post_returns_error_response_on_checker_failure() {
		$this->loadTrackbackFile();
		$this->enableStrictMode();

		if ( ! class_exists( 'WP_Error', false ) ) {
			require_once __DIR__ . '/stubs/class-wp-error.php';
		}
		Filters\expectApplied( 'ppf_pingback_remote_ip' )
			->once()
			->andReturnFirstArg();
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( false );
		Functions\expect( 'PPF\\Pingback\\is_wp_error' )
			->once()
			->andReturnUsing(
				function ( $value ) {
					return $value instanceof \WP_Error;
				}
			);
		Functions\expect( 'trackback_response' )
			->once()
			->with( 1, 'PPF authorization failed.' );

		\PPF\Pingback\ppf_pre_trackback_post( 123, 'https://source.example', '', '', '', '' );
	}

	/**
	 * Ensure trackback_post stores the last PPF result in comment meta.
	 *
	 * @return void
	 */
	public function test_ppf_trackback_post_adds_ppf_result_comment_meta() {
		$this->loadTrackbackFile();

		$GLOBALS['ppf_checker'] = new class() {
			/**
			 * Return a fake authorization result.
			 *
			 * @return string
			 */
			public function get_result() {
				return PPF_Result::MATCH;
			}
		};

		Functions\expect( 'PPF\\Pingback\\add_comment_meta' )
			->once()
			->with( 42, 'ppf_result', PPF_Result::MATCH, true );

		\PPF\Pingback\ppf_trackback_post( 42 );
	}
}
