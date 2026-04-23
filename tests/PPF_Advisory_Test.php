<?php
/**
 * Tests for PPF Advisory.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PPF\Pingback\PPF_Advisory;
use PHPUnit\Framework\Attibutes\CoversMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

require_once dirname( __DIR__ ) . '/wp-includes/ppf/class-ppf-advisory.php';

/**
 * Coverage for PPF Advisory.
 *
 * @group ppf-advisory
 */
#[CoversClass( PPF_Advisory::class )]
class PPF_Advisory_Test extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Advisory instance.
	 *
	 * @var PPF_Advisory
	 */
	private $advisory;

	/**
	 * Set up Brain Monkey and advisory instance.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		unset( $_SERVER['HTTP_X_PINGBACK_ADVISORY'], $_SERVER['HTTP_USER_AGENT'] );
		$this->advisory = new PPF_Advisory();
	}

	/**
	 * Tear down: clear server vars and Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $_SERVER['HTTP_X_PINGBACK_ADVISORY'], $_SERVER['HTTP_USER_AGENT'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Hook pre_remote_get() does nothing when X-Pingback-Advisory header is absent.
	 *
	 * @return void
	 */
	public function test_pre_remote_get_does_nothing_without_header() {
		$http_api_args = array( 'headers' => array() );

		$result = $this->advisory->pre_remote_get( $http_api_args );
		$meta   = $this->advisory->pre_comment_meta( array() );

		$this->assertSame( $http_api_args, $result );
		$this->assertSame( array(), $meta );
	}

	/**
	 * Hook pre_remote_get() sets advisory_raw and parses comma-separated tokens.
	 *
	 * @return void
	 */
	public function test_pre_remote_get_sets_advisory_raw_and_parses_tokens() {
		$_SERVER['HTTP_X_PINGBACK_ADVISORY'] = 'private,automated';
		Functions\expect( 'wp_unslash' )
			->once()
			->with( 'private,automated' )
			->andReturnFirstArg();
		Filters\expectApplied( 'ppf_advisory_token-private' )
			->once()
			->andReturnUsing(
				function ( $args ) {
					return $this->advisory->token_private( $args );
				}
			);
		Filters\expectApplied( 'ppf_advisory_token-automated' )
			->once()
			->andReturnUsing(
				function ( $args ) {
					return $this->advisory->token_automated( $args );
				}
			);
		Filters\expectApplied( 'ppf_advisory_token' )
			->twice()
			->andReturnFirstArg();

		$http_api_args = array();
		$this->advisory->pre_remote_get( $http_api_args );
		$meta = $this->advisory->pre_comment_meta( array() );

		$this->assertSame( 'private,automated', $meta['advisory_raw'] );
		$this->assertTrue( $meta['advisory_private'] );
		$this->assertTrue( $meta['advisory_automated'] );
	}

	/**
	 * Token "auth:basic" with value sets meta and Authorization header.
	 *
	 * @return void
	 */
	public function test_pre_remote_get_auth_basic_with_value_sets_meta_and_header() {
		$_SERVER['HTTP_X_PINGBACK_ADVISORY'] = 'auth:basic=dGVzdDp0ZXN0';
		Functions\expect( 'wp_unslash' )
			->once()
			->andReturnFirstArg();
		Filters\expectApplied( 'ppf_advisory_token-auth:basic' )
			->once()
			->andReturnUsing(
				function ( $args, $value ) {
					return $this->advisory->token_auth_basic( $args, $value );
				}
			);
		Filters\expectApplied( 'ppf_advisory_token' )
			->once()
			->andReturnFirstArg();

		$http_api_args = array( 'headers' => array() );
		$result        = $this->advisory->pre_remote_get( $http_api_args );
		$meta          = $this->advisory->pre_comment_meta( array() );

		$this->assertSame( 'dGVzdDp0ZXN0', $meta['advisory_auth_basic'] );
		$this->assertSame( 'Basic dGVzdDp0ZXN0', $result['headers']['Authorization'] );
	}

	/**
	 * Token "auth:basic" without value sets advisory_auth_basic to true only.
	 *
	 * @return void
	 */
	public function test_pre_remote_get_auth_basic_without_value_sets_meta_true() {
		$_SERVER['HTTP_X_PINGBACK_ADVISORY'] = 'auth:basic';
		Functions\expect( 'wp_unslash' )
			->once()
			->andReturnFirstArg();
		Filters\expectApplied( 'ppf_advisory_token-auth:basic' )
			->once()
			->andReturnUsing(
				function ( $args, $value ) {
					return $this->advisory->token_auth_basic( $args, $value );
				}
			);
		Filters\expectApplied( 'ppf_advisory_token' )
			->once()
			->andReturnFirstArg();

		$http_api_args = array( 'headers' => array() );
		$result        = $this->advisory->pre_remote_get( $http_api_args );
		$meta          = $this->advisory->pre_comment_meta( array() );

		$this->assertTrue( $meta['advisory_auth_basic'] );
		$this->assertArrayNotHasKey( 'Authorization', $result['headers'] );
	}

	/**
	 * Token "auth:bearer" with value sets meta and Authorization header.
	 *
	 * @return void
	 */
	public function test_pre_remote_get_auth_bearer_with_value_sets_meta_and_header() {
		$_SERVER['HTTP_X_PINGBACK_ADVISORY'] = 'auth:bearer=abc123token';
		Functions\expect( 'wp_unslash' )
			->once()
			->andReturnFirstArg();
		Filters\expectApplied( 'ppf_advisory_token-auth:bearer' )
			->once()
			->andReturnUsing(
				function ( $args, $value ) {
					return $this->advisory->token_auth_bearer( $args, $value );
				}
			);
		Filters\expectApplied( 'ppf_advisory_token' )
			->once()
			->andReturnFirstArg();

		$http_api_args = array( 'headers' => array() );
		$result        = $this->advisory->pre_remote_get( $http_api_args );
		$meta          = $this->advisory->pre_comment_meta( array() );

		$this->assertSame( 'abc123token', $meta['advisory_auth_bearer'] );
		$this->assertSame( 'Bearer abc123token', $result['headers']['Authorization'] );
	}

	/**
	 * Token "auth:bearer" without value sets advisory_auth_bearer to true only.
	 *
	 * @return void
	 */
	public function test_pre_remote_get_auth_bearer_without_value_sets_meta_true() {
		$_SERVER['HTTP_X_PINGBACK_ADVISORY'] = 'auth:bearer';
		Functions\expect( 'wp_unslash' )
			->once()
			->andReturnFirstArg();
		Filters\expectApplied( 'ppf_advisory_token-auth:bearer' )
			->once()
			->andReturnUsing(
				function ( $args, $value ) {
					return $this->advisory->token_auth_bearer( $args, $value );
				}
			);
		Filters\expectApplied( 'ppf_advisory_token' )
			->once()
			->andReturnFirstArg();

		$http_api_args = array( 'headers' => array() );
		$result        = $this->advisory->pre_remote_get( $http_api_args );
		$meta          = $this->advisory->pre_comment_meta( array() );

		$this->assertTrue( $meta['advisory_auth_bearer'] );
		$this->assertArrayNotHasKey( 'Authorization', $result['headers'] );
	}

	/**
	 * Token "automated" sets advisory_automated and advisory_user_agent when present.
	 *
	 * @return void
	 */
	public function test_pre_remote_get_automated_sets_meta_and_user_agent_when_present() {
		$_SERVER['HTTP_X_PINGBACK_ADVISORY'] = 'automated';
		$_SERVER['HTTP_USER_AGENT']          = 'Bot/1.0';
		Functions\expect( 'wp_unslash' )
			->twice()
			->andReturnFirstArg();
		Filters\expectApplied( 'ppf_advisory_token-automated' )
			->once()
			->andReturnUsing(
				function ( $args ) {
					return $this->advisory->token_automated( $args );
				}
			);
		Filters\expectApplied( 'ppf_advisory_token' )
			->once()
			->andReturnFirstArg();

		$this->advisory->pre_remote_get( array() );
		$meta = $this->advisory->pre_comment_meta( array() );

		$this->assertTrue( $meta['advisory_automated'] );
		$this->assertSame( 'Bot/1.0', $meta['advisory_user_agent'] );
	}

	/**
	 * Token "automated" sets advisory_automated only when User-Agent absent.
	 *
	 * @return void
	 */
	public function test_pre_remote_get_automated_sets_meta_without_user_agent() {
		$_SERVER['HTTP_X_PINGBACK_ADVISORY'] = 'automated';
		Functions\expect( 'wp_unslash' )
			->once()
			->andReturnFirstArg();
		Filters\expectApplied( 'ppf_advisory_token-automated' )
			->once()
			->andReturnUsing(
				function ( $args ) {
					return $this->advisory->token_automated( $args );
				}
			);
		Filters\expectApplied( 'ppf_advisory_token' )
			->once()
			->andReturnFirstArg();

		$this->advisory->pre_remote_get( array() );
		$meta = $this->advisory->pre_comment_meta( array() );

		$this->assertTrue( $meta['advisory_automated'] );
		$this->assertArrayNotHasKey( 'advisory_user_agent', $meta );
	}

	/**
	 * Token "private" sets advisory_private.
	 *
	 * @return void
	 */
	public function test_pre_remote_get_private_sets_meta() {
		$_SERVER['HTTP_X_PINGBACK_ADVISORY'] = 'private';
		Functions\expect( 'wp_unslash' )
			->once()
			->andReturnFirstArg();
		Filters\expectApplied( 'ppf_advisory_token-private' )
			->once()
			->andReturnUsing(
				function ( $args ) {
					return $this->advisory->token_private( $args );
				}
			);
		Filters\expectApplied( 'ppf_advisory_token' )
			->once()
			->andReturnFirstArg();

		$this->advisory->pre_remote_get( array() );
		$meta = $this->advisory->pre_comment_meta( array() );

		$this->assertTrue( $meta['advisory_private'] );
	}

	/**
	 * Hook pre_remote_get() applies token-specific and ppf_advisory_token filter per token.
	 *
	 * @return void
	 */
	public function test_pre_remote_get_applies_filters_per_token() {
		$_SERVER['HTTP_X_PINGBACK_ADVISORY'] = 'private,unknown:custom';
		Functions\expect( 'wp_unslash' )
			->once()
			->andReturnFirstArg();
		Filters\expectApplied( 'ppf_advisory_token-private' )
			->once()
			->andReturnUsing(
				function ( $args ) {
					return $this->advisory->token_private( $args );
				}
			);
		Filters\expectApplied( 'ppf_advisory_token' )
			->twice()
			->andReturnFirstArg();

		$this->advisory->pre_remote_get( array() );
		$meta = $this->advisory->pre_comment_meta( array() );

		$this->assertTrue( $meta['advisory_private'] );
		$this->assertSame( 'private,unknown:custom', $meta['advisory_raw'] );
	}

	/**
	 * Unknown token does not break processing; generic filter still receives it.
	 *
	 * @return void
	 */
	public function test_pre_remote_get_unknown_token_does_not_break() {
		$_SERVER['HTTP_X_PINGBACK_ADVISORY'] = 'custom:value';
		Functions\expect( 'wp_unslash' )
			->once()
			->andReturnFirstArg();
		Filters\expectApplied( 'ppf_advisory_token-custom:value' )
			->once()
			->andReturnFirstArg();
		Filters\expectApplied( 'ppf_advisory_token' )
			->once()
			->with( \Mockery::type( 'array' ), 'custom:value', null )
			->andReturnFirstArg();

		$http_api_args = array();
		$this->advisory->pre_remote_get( $http_api_args );
		$meta = $this->advisory->pre_comment_meta( array() );

		$this->assertSame( 'custom:value', $meta['advisory_raw'] );
		$this->assertArrayNotHasKey( 'advisory_private', $meta );
	}

	/**
	 * Tokens are case-insensitive (auth:basic vs AUTH:BASIC).
	 *
	 * @return void
	 */
	public function test_pre_remote_get_token_case_insensitive() {
		$_SERVER['HTTP_X_PINGBACK_ADVISORY'] = 'AUTH:BASIC=foo';
		Functions\expect( 'wp_unslash' )
			->once()
			->andReturnFirstArg();
		Filters\expectApplied( 'ppf_advisory_token-auth:basic' )
			->once()
			->andReturnUsing(
				function ( $args, $value ) {
					return $this->advisory->token_auth_basic( $args, $value );
				}
			);
		Filters\expectApplied( 'ppf_advisory_token' )
			->once()
			->andReturnFirstArg();

		$http_api_args = array( 'headers' => array() );
		$result        = $this->advisory->pre_remote_get( $http_api_args );
		$meta          = $this->advisory->pre_comment_meta( array() );

		$this->assertSame( 'foo', $meta['advisory_auth_basic'] );
		$this->assertSame( 'Basic foo', $result['headers']['Authorization'] );
	}

	/**
	 * Multiple tokens in one header are all applied.
	 *
	 * @return void
	 */
	public function test_pre_remote_get_multiple_tokens_applied() {
		$_SERVER['HTTP_X_PINGBACK_ADVISORY'] = 'private, automated , auth:bearer=token1';
		$_SERVER['HTTP_USER_AGENT']          = 'Crawler';
		Functions\expect( 'wp_unslash' )
			->twice()
			->andReturnFirstArg();
		Filters\expectApplied( 'ppf_advisory_token-private' )
			->once()
			->andReturnUsing(
				function ( $args ) {
					return $this->advisory->token_private( $args );
				}
			);
		Filters\expectApplied( 'ppf_advisory_token-automated' )
			->once()
			->andReturnUsing(
				function ( $args ) {
					return $this->advisory->token_automated( $args );
				}
			);
		Filters\expectApplied( 'ppf_advisory_token-auth:bearer' )
			->once()
			->andReturnUsing(
				function ( $args, $value ) {
					return $this->advisory->token_auth_bearer( $args, $value );
				}
			);
		Filters\expectApplied( 'ppf_advisory_token' )
			->times( 3 )
			->andReturnFirstArg();

		$http_api_args = array( 'headers' => array() );
		$result        = $this->advisory->pre_remote_get( $http_api_args );
		$meta          = $this->advisory->pre_comment_meta( array() );

		$this->assertTrue( $meta['advisory_private'] );
		$this->assertTrue( $meta['advisory_automated'] );
		$this->assertSame( 'Crawler', $meta['advisory_user_agent'] );
		$this->assertSame( 'token1', $meta['advisory_auth_bearer'] );
		$this->assertSame( 'Bearer token1', $result['headers']['Authorization'] );
	}

	/**
	 * Hook pre_comment_meta() merges existing comment_meta with advisory data.
	 *
	 * @return void
	 */
	public function test_pre_comment_meta_merges_advisory_into_passed_meta() {
		$this->advisory->token_private( array() );
		$existing = array( 'comment_author' => 'Alice' );

		$meta = $this->advisory->pre_comment_meta( $existing );

		$this->assertSame( 'Alice', $meta['comment_author'] );
		$this->assertTrue( $meta['advisory_private'] );
	}
}
