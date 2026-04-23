<?php
/**
 * Tests for PPF checker.
 *
 * @package PPF_Pingback_Direct
 */

// phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use Brain\Monkey\Functions;
use Brain\Monkey;
use Brain\Monkey\Filters;
use PPF\Pingback\PPF_Advisory;
use PPF\Pingback\PPF_Checker;
use PPF\Pingback\PPF_IP_Helper;
use PPF\Pingback\PPF_Result;
use PPF\Pingback\PPF_Logger;
use PPF\Pingback\PPF_Record;
use PPF\Pingback\PPF_Record_Default_Resolver;
use PPF\Pingback\PPF_Token_Matcher_Switch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

require_once dirname( __DIR__ ) . '/wp-includes/ppf/class-ppf-checker.php';
require_once __DIR__ . '/class-ppf-checker-test-ip-helper.php';
require_once __DIR__ . '/class-ppf-checker-test-matcher.php';
require_once __DIR__ . '/class-ppf-checker-test-record.php';

/**
 * Coverage for PPF checker.
 *
 * @group ppf-checker
 */
#[CoversClass( PPF_Checker::class )]
#[UsesClass( PPF_Advisory::class )]
#[UsesClass( PPF_IP_Helper::class )]
#[UsesClass( PPF_Logger::class )]
#[UsesClass( PPF_Record::class )]
#[UsesClass( PPF_Record_Default_Resolver::class )]
#[UsesClass( PPF_Token_Matcher_Switch::class )]
class PPF_Checker_Test extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Checker instance.
	 *
	 * @var PPF_Checker
	 */
	private $checker;

	/**
	 * Set up test dependencies.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->checker = new PPF_Checker();
	}

	/**
	 * Clean up Brain Monkey.
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
	protected function enableStrictMode() {
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
	 * Validate check() returns WP_Error when source is unwelcome.
	 *
	 * @return void
	 */
	public function test_check_rejects_unwelcome_source() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( array( 'host' => 'source.example' ) );
		Filters\expectApplied( 'ppf_pingback_remote_ip' )
			->once()
			->with( false, 'https://source.example', 'https://target.example', array() )
			->andReturn( '192.0.2.1' );
		Filters\expectApplied( 'ppf_pingback_unwelcome' )
			->once()
			->andReturn( true );
		Mockery::mock( \WP_Error::class );

		$result = $this->checker->check( 'https://source.example', 'https://target.example', array() );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Validate check() returns false when host is invalid.
	 *
	 * @return void
	 */
	public function test_check_returns_false_on_invalid_host() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( false );

		$checker = new PPF_Checker(
			new PPF_Checker_Test_IP_Helper( '192.0.2.1' ),
			new PPF_Checker_Test_Matcher( PPF_Result::MATCH ),
			new PPF_Checker_Test_Record( null )
		);

		$this->assertFalse( $checker->check( 'https://source.example', 'https://target.example', array() ) );
	}

	/**
	 * Validate check() returns WP_Error when strict and host is invalid.
	 *
	 * @return void
	 */
	public function test_check_returns_wp_error_when_strict_and_invalid_host() {
		$this->enableStrictMode();

		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( false );
		Mockery::mock( \WP_Error::class );

		$checker = new PPF_Checker(
			new PPF_Checker_Test_IP_Helper( '192.0.2.1' ),
			new PPF_Checker_Test_Matcher( PPF_Result::MATCH ),
			new PPF_Checker_Test_Record( null )
		);

		$this->assertInstanceOf( \WP_Error::class, $checker->check( 'https://source.example', 'https://target.example', array() ) );
	}

	/**
	 * Validate check() returns false when sender IP missing.
	 *
	 * @return void
	 */
	public function test_check_returns_false_on_missing_remote_ip() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( array( 'host' => 'source.example' ) );

		$checker = new PPF_Checker(
			new PPF_Checker_Test_IP_Helper( false ),
			new PPF_Checker_Test_Matcher( PPF_Result::MATCH ),
			new PPF_Checker_Test_Record( null )
		);

		$this->assertFalse( $checker->check( 'https://source.example', 'https://target.example', array() ) );
	}

	/**
	 * Validate check() returns WP_Error when strict and sender IP missing.
	 *
	 * @return void
	 */
	public function test_check_returns_wp_error_when_strict_and_missing_remote_ip() {
		$this->enableStrictMode();

		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( array( 'host' => 'source.example' ) );
		Mockery::mock( \WP_Error::class );

		$checker = new PPF_Checker(
			new PPF_Checker_Test_IP_Helper( false ),
			new PPF_Checker_Test_Matcher( PPF_Result::MATCH ),
			new PPF_Checker_Test_Record( null )
		);

		$this->assertInstanceOf( \WP_Error::class, $checker->check( 'https://source.example', 'https://target.example', array() ) );
	}

	/**
	 * Validate check() can skip authorization when requested.
	 *
	 * @return void
	 */
	public function test_check_skips_authorization() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( array( 'host' => 'source.example' ) );
		Filters\expectApplied( 'ppf_pingback_remote_ip' )
			->once()
			->with( false, 'https://source.example', 'https://target.example', array() )
			->andReturn( '192.0.2.1' );
		Filters\expectApplied( 'ppf_pingback_unwelcome' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_skip' )
			->once()
			->andReturn( true );
		Filters\expectApplied( 'ppf_authorization_after' )
			->once()
			->andReturn( '192.0.2.1' );

		$result = $this->checker->check( 'https://source.example', 'https://target.example', array() );
		$this->assertSame( '192.0.2.1', $result );
	}

	/**
	 * Validate check() returns WP_Error on no match.
	 *
	 * @return void
	 */
	public function test_check_returns_wp_error_on_no_match() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( array( 'host' => 'source.example' ) );
		Filters\expectApplied( 'ppf_pingback_unwelcome' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_skip' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_result' )
			->once()
			->andReturn( PPF_Result::NO_MATCH );
		Functions\expect( 'PPF\\Pingback\\is_wp_error' )
			->once()
			->andReturn( false );
		Mockery::mock( \WP_Error::class );

		$checker = new PPF_Checker(
			new PPF_Checker_Test_IP_Helper( '192.0.2.1' ),
			new PPF_Checker_Test_Matcher( PPF_Result::NO_MATCH ),
			new PPF_Checker_Test_Record( 'v=ppf1 ip4:192.0.2.0/24' )
		);

		$this->assertInstanceOf( \WP_Error::class, $checker->check( 'https://source.example', 'https://target.example', array() ) );
	}

	/**
	 * Validate result filter can return WP_Error.
	 *
	 * @return void
	 */
	public function test_check_returns_wp_error_from_result_filter() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( array( 'host' => 'source.example' ) );
		Filters\expectApplied( 'ppf_pingback_unwelcome' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_skip' )
			->once()
			->andReturn( false );
		Mockery::mock( \WP_Error::class );

		$error = new \WP_Error();
		Filters\expectApplied( 'ppf_authorization_result' )
			->once()
			->andReturn( $error );
		Functions\expect( 'PPF\\Pingback\\is_wp_error' )
			->once()
			->andReturn( true );

		$checker = new PPF_Checker(
			new PPF_Checker_Test_IP_Helper( '192.0.2.1' ),
			new PPF_Checker_Test_Matcher( PPF_Result::MATCH ),
			new PPF_Checker_Test_Record( 'v=ppf1 ip4:192.0.2.0/24' )
		);

		$this->assertSame( $error, $checker->check( 'https://source.example', 'https://target.example', array() ) );
	}

	/**
	 * Validate strict mode rejects missing record.
	 *
	 * @return void
	 */
	public function test_check_strict_mode_rejects_no_record() {
		$this->enableStrictMode();
		Mockery::mock( \WP_Error::class );

		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( array( 'host' => 'source.example' ) );
		Filters\expectApplied( 'ppf_pingback_unwelcome' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_skip' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_result' )
			->once()
			->andReturn( PPF_Result::NO_RECORD );
		Functions\expect( 'PPF\\Pingback\\is_wp_error' )
			->once()
			->andReturn( false );

		$checker = new PPF_Checker(
			new PPF_Checker_Test_IP_Helper( '192.0.2.1' ),
			new PPF_Checker_Test_Matcher( PPF_Result::NO_RECORD ),
			new PPF_Checker_Test_Record( null )
		);

		$this->assertInstanceOf( \WP_Error::class, $checker->check( 'https://source.example', 'https://target.example', array() ) );
	}

	/**
	 * Validate invalid results fall through when not strict.
	 *
	 * @return void
	 */
	public function test_check_returns_remote_ip_on_invalid_when_not_strict() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( array( 'host' => 'source.example' ) );
		Filters\expectApplied( 'ppf_pingback_unwelcome' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_skip' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_result' )
			->once()
			->andReturn( PPF_Result::INVALID );
		Functions\expect( 'PPF\\Pingback\\is_wp_error' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_after' )
			->once()
			->andReturn( '192.0.2.1' );

		$checker = new PPF_Checker(
			new PPF_Checker_Test_IP_Helper( '192.0.2.1' ),
			new PPF_Checker_Test_Matcher( PPF_Result::INVALID ),
			new PPF_Checker_Test_Record( null )
		);

		$this->assertSame( '192.0.2.1', $checker->check( 'https://source.example', 'https://target.example', array() ) );
	}

	/**
	 * Validate RESULT_ERROR falls through when not strict.
	 *
	 * @return void
	 */
	public function test_check_returns_remote_ip_on_error_when_not_strict() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( array( 'host' => 'source.example' ) );
		Filters\expectApplied( 'ppf_pingback_unwelcome' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_skip' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_result' )
			->once()
			->andReturn( PPF_Result::ERROR );
		Functions\expect( 'PPF\\Pingback\\is_wp_error' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_after' )
			->once()
			->andReturn( '192.0.2.1' );

		$checker = new PPF_Checker(
			new PPF_Checker_Test_IP_Helper( '192.0.2.1' ),
			new PPF_Checker_Test_Matcher( PPF_Result::ERROR ),
			new PPF_Checker_Test_Record( null )
		);

		$this->assertSame( '192.0.2.1', $checker->check( 'https://source.example', 'https://target.example', array() ) );
	}

	/**
	 * Validate check() returns remote IP on RESULT_NONE.
	 *
	 * @return void
	 */
	public function test_check_returns_remote_ip_on_result_none() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( array( 'host' => 'source.example' ) );
		Filters\expectApplied( 'ppf_pingback_unwelcome' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_skip' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_result' )
			->once()
			->andReturn( PPF_Result::NONE );
		Functions\expect( 'PPF\\Pingback\\is_wp_error' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_after' )
			->once()
			->andReturn( '192.0.2.1' );

		$checker = new PPF_Checker(
			new PPF_Checker_Test_IP_Helper( '192.0.2.1' ),
			new PPF_Checker_Test_Matcher( PPF_Result::NONE ),
			new PPF_Checker_Test_Record( null )
		);

		$this->assertSame( '192.0.2.1', $checker->check( 'https://source.example', 'https://target.example', array() ) );
	}

	/**
	 * Validate check() returns remote IP on RESULT_UNKNOWN.
	 *
	 * @return void
	 */
	public function test_check_returns_remote_ip_on_result_unknown() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( array( 'host' => 'source.example' ) );
		Filters\expectApplied( 'ppf_pingback_unwelcome' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_skip' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_result' )
			->once()
			->andReturn( PPF_Result::UNKNOWN );
		Functions\expect( 'PPF\\Pingback\\is_wp_error' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_after' )
			->once()
			->andReturn( '192.0.2.1' );

		$checker = new PPF_Checker(
			new PPF_Checker_Test_IP_Helper( '192.0.2.1' ),
			new PPF_Checker_Test_Matcher( PPF_Result::UNKNOWN ),
			new PPF_Checker_Test_Record( null )
		);

		$this->assertSame( '192.0.2.1', $checker->check( 'https://source.example', 'https://target.example', array() ) );
	}

	/**
	 * Validate invalid result values trigger errors.
	 *
	 * @return void
	 */
	public function test_check_returns_wp_error_on_invalid_result_value() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( array( 'host' => 'source.example' ) );
		Filters\expectApplied( 'ppf_pingback_unwelcome' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_skip' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_result' )
			->once()
			->andReturn( 'bogus' );
		Functions\expect( 'PPF\\Pingback\\is_wp_error' )
			->once()
			->andReturn( false );
		Functions\expect( 'PPF\\Pingback\\_doing_it_wrong' )
			->once();

		$checker = new PPF_Checker(
			new PPF_Checker_Test_IP_Helper( '192.0.2.1' ),
			new PPF_Checker_Test_Matcher( PPF_Result::MATCH ),
			new PPF_Checker_Test_Record( 'v=ppf1 ip4:192.0.2.0/24' )
		);

		$this->assertInstanceOf( \WP_Error::class, $checker->check( 'https://source.example', 'https://target.example', array() ) );
	}

	/**
	 * Validate check() returns remote IP on match.
	 *
	 * @return void
	 */
	public function test_check_returns_remote_ip_on_match() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( array( 'host' => 'source.example' ) );
		Filters\expectApplied( 'ppf_pingback_unwelcome' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_skip' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_result' )
			->once()
			->andReturn( PPF_Result::MATCH );
		Functions\expect( 'PPF\\Pingback\\is_wp_error' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_after' )
			->once()
			->andReturn( '192.0.2.1' );

		$checker = new PPF_Checker(
			new PPF_Checker_Test_IP_Helper( '192.0.2.1' ),
			new PPF_Checker_Test_Matcher( PPF_Result::MATCH ),
			new PPF_Checker_Test_Record( 'v=ppf1 ip4:192.0.2.0/24' )
		);

		$this->assertSame( '192.0.2.1', $checker->check( 'https://source.example', 'https://target.example', array() ) );
	}

	/**
	 * Validate get_result returns unknown before any checks.
	 *
	 * @return void
	 */
	public function test_get_result_returns_unknown_initially() {
		$this->assertSame( PPF_Result::UNKNOWN, $this->checker->get_result() );
	}

	/**
	 * Validate get_result returns last authorization result after check.
	 *
	 * @return void
	 */
	public function test_get_result_returns_last_result_after_check() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'https://source.example' )
			->andReturn( array( 'host' => 'source.example' ) );
		Filters\expectApplied( 'ppf_pingback_unwelcome' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_skip' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_result' )
			->once()
			->andReturn( PPF_Result::MATCH );
		Functions\expect( 'PPF\\Pingback\\is_wp_error' )
			->once()
			->andReturn( false );
		Filters\expectApplied( 'ppf_authorization_after' )
			->once()
			->andReturn( '192.0.2.1' );

		$checker = new PPF_Checker(
			new PPF_Checker_Test_IP_Helper( '192.0.2.1' ),
			new PPF_Checker_Test_Matcher( PPF_Result::MATCH ),
			new PPF_Checker_Test_Record( 'v=ppf1 ip4:192.0.2.0/24' )
		);
		$checker->check( 'https://source.example', 'https://target.example', array() );

		$this->assertSame( PPF_Result::MATCH, $checker->get_result() );
	}

	/**
	 * Validate parse_url_host handles invalid inputs.
	 *
	 * @return void
	 */
	public function test_parse_url_host_invalid_inputs() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'http://example.com' )
			->andReturn( array() );

		$this->assertFalse( $this->checker->parse_url_host( '' ) );
		$this->assertFalse( $this->checker->parse_url_host( 'http://example.com' ) );
	}

	/**
	 * Validate parse_url_host returns false when host is empty string.
	 *
	 * @return void
	 */
	public function test_parse_url_host_returns_false_when_host_empty() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'http:///path' )
			->andReturn( array( 'host' => '' ) );

		$this->assertFalse( $this->checker->parse_url_host( 'http:///path' ) );
	}

	/**
	 * Validate parse_url_host normalizes hostnames.
	 *
	 * @return void
	 */
	public function test_parse_url_host_normalizes_host() {
		Functions\expect( 'PPF\\Pingback\\wp_parse_url' )
			->once()
			->with( 'http://Example.com.' )
			->andReturn( array( 'host' => 'Example.com.' ) );

		$this->assertSame( 'example.com', $this->checker->parse_url_host( 'http://Example.com.' ) );
	}
}
