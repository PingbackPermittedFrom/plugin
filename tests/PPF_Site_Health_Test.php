<?php
/**
 * Tests for PPF Site Health integration.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PPF\Pingback\PPF_Record;
use PPF\Pingback\PPF_Record_Resolver;
use PPF\Pingback\PPF_Site_Health;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

require_once dirname( __DIR__ ) . '/wp-includes/ppf/class-ppf-site-health.php';
require_once __DIR__ . '/class-ppf-record-fake-resolver.php';
require_once __DIR__ . '/class-ppf-site-health-test-conflict-callback.php';

/**
 * Coverage for Site Health registration and trusted proxy reporting.
 *
 * @group ppf-site-health
 */
#[CoversClass( PPF_Site_Health::class )]
#[UsesClass( PPF_Record::class )]
class PPF_Site_Health_Test extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		unset( $GLOBALS['wp_filter'] );
	}

	/**
	 * Tear down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		unset( $GLOBALS['wp_filter'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Set the forwarded-for header for a test.
	 *
	 * @param string $value Header value.
	 * @return void
	 */
	protected function withForwardedForHeader( string $value ): void {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = $value;
	}

	/**
	 * Configure the trusted proxies constant for a test.
	 *
	 * @param mixed $value Constant value.
	 * @return void
	 */
	protected function withTrustedProxies( $value ): void {
		Functions\when( 'defined' )->alias(
			function ( $constant ) {
				if ( 'PPF_TRUSTED_PROXIES' === $constant ) {
					return true;
				}
				return _defined( $constant );
			}
		);
		Functions\when( 'constant' )->alias(
			function ( $constant ) use ( $value ) {
				if ( 'PPF_TRUSTED_PROXIES' === $constant ) {
					return $value;
				}
				return _constant( $constant );
			}
		);
	}

	/**
	 * Ensure Site Health registers both PPF checks.
	 *
	 * @return void
	 */
	public function test_add_tests_registers_record_and_trusted_proxy_checks() {
		Functions\when( '__' )->returnArg();

		$tests  = array( 'direct' => array() );
		$result = PPF_Site_Health::add_tests( $tests );

		$this->assertArrayHasKey( PPF_Site_Health::TEST_PPF_RECORD, $result['direct'] );
		$this->assertArrayHasKey( PPF_Site_Health::TEST_PPF_TRUSTED_PROXIES, $result['direct'] );
		$this->assertArrayHasKey( PPF_Site_Health::TEST_PPF_XMLRPC_SERVER, $result['direct'] );
	}

	/**
	 * Ensure the XML-RPC server test reports success when PPF owns the server class.
	 *
	 * @return void
	 */
	public function test_run_ppf_xmlrpc_server_test_reports_active_ppf_server() {
		Functions\when( '__' )->returnArg();
		Filters\expectApplied( 'wp_xmlrpc_server_class' )
			->once()
			->with( 'wp_xmlrpc_server' )
			->andReturn( 'PPF\\Pingback\\wp_xmlrpc_server' );

		$result = PPF_Site_Health::run_ppf_xmlrpc_server_test();

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( PPF_Site_Health::TEST_PPF_XMLRPC_SERVER, $result['test'] );
	}

	/**
	 * Ensure the XML-RPC server test reports a critical issue when core remains active.
	 *
	 * @return void
	 */
	public function test_run_ppf_xmlrpc_server_test_reports_core_server_as_critical() {
		Functions\when( '__' )->returnArg();
		Filters\expectApplied( 'wp_xmlrpc_server_class' )
			->once()
			->with( 'wp_xmlrpc_server' )
			->andReturn( 'wp_xmlrpc_server' );

		$result = PPF_Site_Health::run_ppf_xmlrpc_server_test();

		$this->assertSame( 'critical', $result['status'] );
	}

	/**
	 * Ensure the XML-RPC server test reports a conflicting override and its source when possible.
	 *
	 * @return void
	 */
	public function test_run_ppf_xmlrpc_server_test_reports_conflicting_override_source() {
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Filters\expectApplied( 'wp_xmlrpc_server_class' )
			->once()
			->with( 'wp_xmlrpc_server' )
			->andReturn( 'Vendor\\Security\\XmlRpc_Server' );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional hook fixture for conflict detection.
		$GLOBALS['wp_filter'] = array(
			'wp_xmlrpc_server_class' => (object) array(
				'callbacks' => array(
					10          => array(
						array(
							'function' => __NAMESPACE__ . '\\ppf_site_health_test_conflict_callback',
						),
					),
					PHP_INT_MAX => array(
						array(
							'function' => 'PPF\\Pingback\\ppf_xmlrpc_server_class',
						),
					),
				),
			),
		);

		$result      = PPF_Site_Health::run_ppf_xmlrpc_server_test();
		$description = $result['description'];

		$this->assertSame( 'critical', $result['status'] );
		$this->assertIsString( $description );
		$this->assertStringContainsString( 'Vendor\\Security\\XmlRpc_Server', $description );
		$this->assertStringContainsString( 'ppf_site_health_test_conflict_callback', $description );
	}

	/**
	 * Malformed wp_xmlrpc_server_class hook shapes: must not throw; must return critical + string description.
	 *
	 * @param array<string, mixed>|null $wp_filter_fixture Value for $GLOBALS['wp_filter'] (null = do not set).
	 * @return void
	 */
	#[DataProvider( 'provide_malformed_wp_filter_fixtures_for_xmlrpc_conflict' )]
	public function test_run_ppf_xmlrpc_server_test_survives_malformed_wp_filter( ?array $wp_filter_fixture ): void {
		$this->stubThirdPartyXmlRpcConflictStubs();

		if ( null !== $wp_filter_fixture ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional hook fixtures for robustness tests.
			$GLOBALS['wp_filter'] = $wp_filter_fixture;
		}

		$result = PPF_Site_Health::run_ppf_xmlrpc_server_test();

		$this->assertXmlrpcConflictRobustnessResult( $result );
	}

	/**
	 * Another plugin override without conflict detail (single-paragraph description).
	 *
	 * @return void
	 */
	public function test_run_ppf_xmlrpc_server_test_third_party_without_hook_uses_fallback_description(): void {
		$this->stubThirdPartyXmlRpcConflictStubs();

		$result = PPF_Site_Health::run_ppf_xmlrpc_server_test();

		$this->assertXmlrpcConflictRobustnessResult( $result );
		$description = $result['description'];
		$this->assertIsString( $description );
		$this->assertStringContainsString( 'Vendor\\Conflict\\Server', $description );
		$this->assertStringNotContainsString( 'appears to come from', $description );
	}

	/**
	 * Legacy array-shaped hook container (WordPress-style array with callbacks key).
	 *
	 * @return void
	 */
	public function test_run_ppf_xmlrpc_server_test_conflict_source_with_legacy_array_hook_container(): void {
		$this->stubThirdPartyXmlRpcConflictStubs();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional hook fixture.
		$GLOBALS['wp_filter'] = array(
			'wp_xmlrpc_server_class' => array(
				'callbacks' => array(
					10          => array(
						array(
							'function' => __NAMESPACE__ . '\\ppf_site_health_test_conflict_callback',
						),
					),
					PHP_INT_MAX => array(
						array(
							'function' => 'PPF\\Pingback\\ppf_xmlrpc_server_class',
						),
					),
				),
			),
		);

		$result = PPF_Site_Health::run_ppf_xmlrpc_server_test();

		$this->assertXmlrpcConflictRobustnessResult( $result );
		$description = $result['description'];
		$this->assertIsString( $description );
		$this->assertStringContainsString( 'appears to come from', $description );
	}

	/**
	 * Callbacks list has no PPF callback; last external should still yield structured output without throwing.
	 *
	 * @return void
	 */
	public function test_run_ppf_xmlrpc_server_test_conflict_without_ppf_callback_in_hook(): void {
		$this->stubThirdPartyXmlRpcConflictStubs();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional hook fixture.
		$GLOBALS['wp_filter'] = array(
			'wp_xmlrpc_server_class' => (object) array(
				'callbacks' => array(
					5 => array(
						array(
							'function' => __NAMESPACE__ . '\\ppf_site_health_test_conflict_callback',
						),
					),
				),
			),
		);

		$result = PPF_Site_Health::run_ppf_xmlrpc_server_test();

		$this->assertXmlrpcConflictRobustnessResult( $result );
		$description = $result['description'];
		$this->assertIsString( $description );
		$this->assertStringContainsString( 'appears to come from', $description );
	}

	/**
	 * Closure callback in hook list: describe_callback must not throw.
	 *
	 * @return void
	 */
	public function test_run_ppf_xmlrpc_server_test_conflict_with_closure_callback_in_hook(): void {
		$this->stubThirdPartyXmlRpcConflictStubs();
		$closure = static function ( $class_name ) {
			return $class_name;
		};
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional hook fixture.
		$GLOBALS['wp_filter'] = array(
			'wp_xmlrpc_server_class' => (object) array(
				'callbacks' => array(
					10          => array(
						array(
							'function' => $closure,
						),
					),
					PHP_INT_MAX => array(
						array(
							'function' => 'PPF\\Pingback\\ppf_xmlrpc_server_class',
						),
					),
				),
			),
		);

		$result = PPF_Site_Health::run_ppf_xmlrpc_server_test();

		$this->assertXmlrpcConflictRobustnessResult( $result );
	}

	/**
	 * Odd array callback shape (invalid callable target) must not throw.
	 *
	 * @return void
	 */
	public function test_run_ppf_xmlrpc_server_test_conflict_with_array_callback_unknown_target(): void {
		$this->stubThirdPartyXmlRpcConflictStubs();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional hook fixture.
		$GLOBALS['wp_filter'] = array(
			'wp_xmlrpc_server_class' => (object) array(
				'callbacks' => array(
					10          => array(
						array(
							'function' => array( null, 'ghostMethod' ),
						),
					),
					PHP_INT_MAX => array(
						array(
							'function' => 'PPF\\Pingback\\ppf_xmlrpc_server_class',
						),
					),
				),
			),
		);

		$result = PPF_Site_Health::run_ppf_xmlrpc_server_test();

		$this->assertXmlrpcConflictRobustnessResult( $result );
	}

	/**
	 * Data provider: messy $wp_filter structures for XML-RPC conflict introspection.
	 *
	 * @return iterable<string, array{array<string, mixed>|null}>
	 */
	public static function provide_malformed_wp_filter_fixtures_for_xmlrpc_conflict(): iterable {
		yield 'wp_filter_global_unset' => array( null );
		yield 'empty_wp_filter' => array( array() );
		yield 'hook_null' => array( array( 'wp_xmlrpc_server_class' => null ) );
		yield 'hook_scalar_string' => array( array( 'wp_xmlrpc_server_class' => 'broken' ) );
		yield 'hook_object_without_callbacks_property' => array( array( 'wp_xmlrpc_server_class' => new \stdClass() ) );
		yield 'callbacks_property_null' => array(
			array(
				'wp_xmlrpc_server_class' => (object) array( 'callbacks' => null ),
			),
		);
		yield 'callbacks_not_array' => array(
			array(
				'wp_xmlrpc_server_class' => (object) array( 'callbacks' => 'not-callback-array' ),
			),
		);
		yield 'callbacks_empty_array' => array(
			array(
				'wp_xmlrpc_server_class' => (object) array( 'callbacks' => array() ),
			),
		);
		yield 'priority_bucket_not_array' => array(
			array(
				'wp_xmlrpc_server_class' => (object) array(
					'callbacks' => array(
						10 => 'not-an-array-of-callbacks',
					),
				),
			),
		);
		yield 'callback_row_not_array' => array(
			array(
				'wp_xmlrpc_server_class' => (object) array(
					'callbacks' => array(
						10 => array(
							'not-callback-array',
							array(
								'function' => __NAMESPACE__ . '\\ppf_site_health_test_conflict_callback',
							),
						),
					),
				),
			),
		);
		yield 'callback_row_missing_function_key' => array(
			array(
				'wp_xmlrpc_server_class' => (object) array(
					'callbacks' => array(
						10 => array(
							array( 'something_else' => 1 ),
						),
					),
				),
			),
		);
		yield 'legacy_array_hook_no_callbacks_key' => array(
			array(
				'wp_xmlrpc_server_class' => array( 'not_callbacks' => array() ),
			),
		);
	}

	/**
	 * Stub filter + i18n for third-party XML-RPC class conflict tests.
	 *
	 * @return void
	 */
	private function stubThirdPartyXmlRpcConflictStubs(): void {
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Filters\expectApplied( 'wp_xmlrpc_server_class' )
			->once()
			->with( 'wp_xmlrpc_server' )
			->andReturn( 'Vendor\\Conflict\\Server' );
	}

	/**
	 * Assert Site Health XML-RPC result is safe after conflict introspection.
	 *
	 * @param array<string, mixed> $result Result array from run_ppf_xmlrpc_server_test().
	 * @return void
	 */
	private function assertXmlrpcConflictRobustnessResult( array $result ): void {
		$this->assertSame( 'critical', $result['status'] );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertIsString( $result['description'] );
		$this->assertNotSame( '', $result['description'] );
		$this->assertArrayHasKey( 'test', $result );
		$this->assertSame( PPF_Site_Health::TEST_PPF_XMLRPC_SERVER, $result['test'] );
	}

	/**
	 * Ensure the record test reports success with v=ppf1 only (no token clauses).
	 *
	 * @return void
	 */
	public function test_run_ppf_record_test_reports_valid_record_without_tokens() {
		$resolver = new PPF_Record_Fake_Resolver();
		$resolver->set_txt_records( '_pingback.example.com', 'v=ppf1' );

		Functions\when( '__' )->returnArg();
		Functions\when( '_x' )->returnArg();
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'PPF\\Pingback\\wp_parse_url' )->justReturn( 'example.com' );
		Functions\when( 'esc_html' )->returnArg();
		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( null )
			->andReturn( $resolver );

		$result      = PPF_Site_Health::run_ppf_record_test();
		$description = $result['description'];

		$this->assertSame( 'good', $result['status'] );
		$this->assertIsString( $description );
		$this->assertStringContainsString( '_pingback.example.com', $description );
		$this->assertStringContainsString( 'none', $description );
	}

	/**
	 * Ensure the record test reports a critical error when home URL is set but host is empty.
	 *
	 * @return void
	 */
	public function test_run_ppf_record_test_reports_missing_host_when_parse_url_empty() {
		Functions\when( '__' )->returnArg();
		Functions\when( 'home_url' )->justReturn( 'https://' );
		Functions\when( 'PPF\\Pingback\\wp_parse_url' )->justReturn( '' );
		Functions\when( 'admin_url' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();

		$result = PPF_Site_Health::run_ppf_record_test();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertSame( PPF_Site_Health::TEST_PPF_RECORD, $result['test'] );
	}

	/**
	 * Ensure the record test reports a critical error when the site host cannot be determined.
	 *
	 * @return void
	 */
	public function test_run_ppf_record_test_reports_missing_site_host() {
		Functions\when( '__' )->returnArg();
		Functions\when( 'home_url' )->justReturn( false );
		Functions\when( 'admin_url' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();

		$result = PPF_Site_Health::run_ppf_record_test();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertSame( PPF_Site_Health::TEST_PPF_RECORD, $result['test'] );
	}

	/**
	 * Ensure the record test reports success for a valid record.
	 *
	 * @return void
	 */
	public function test_run_ppf_record_test_reports_valid_record() {
		$resolver = new PPF_Record_Fake_Resolver();
		$resolver->set_txt_records( '_pingback.example.com', 'v=ppf1 ip4:192.0.2.0/24' );

		Functions\when( '__' )->returnArg();
		Functions\when( '_x' )->returnArg();
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'PPF\\Pingback\\wp_parse_url' )->justReturn( 'example.com' );
		Functions\when( 'esc_html' )->returnArg();
		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( null )
			->andReturn( $resolver );

		$result      = PPF_Site_Health::run_ppf_record_test();
		$description = $result['description'];

		$this->assertSame( 'good', $result['status'] );
		$this->assertIsString( $description );
		$this->assertStringContainsString( '_pingback.example.com', $description );
	}

	/**
	 * Ensure the record test reports a recommendation when no record exists.
	 *
	 * @return void
	 */
	public function test_run_ppf_record_test_reports_no_record() {
		$resolver = new PPF_Record_Fake_Resolver();
		$resolver->set_txt_record_error( '_pingback.example.com', PPF_Record_Resolver::ERROR_NO_RECORDS );

		Functions\when( '__' )->returnArg();
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'PPF\\Pingback\\wp_parse_url' )->justReturn( 'example.com' );
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( null )
			->andReturn( $resolver );

		$result      = PPF_Site_Health::run_ppf_record_test();
		$description = $result['description'];

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertIsString( $description );
		$this->assertStringContainsString( 'No <em>Pingback Permitted From</em>', $description );
	}

	/**
	 * Ensure the record test reports a critical error for invalid records.
	 *
	 * @return void
	 */
	public function test_run_ppf_record_test_reports_invalid_record() {
		$resolver = new PPF_Record_Fake_Resolver();
		$resolver->set_txt_records( '_pingback.example.com', 'v=ppf2 ip4:192.0.2.0/24' );

		Functions\when( '__' )->returnArg();
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'PPF\\Pingback\\wp_parse_url' )->justReturn( 'example.com' );
		Functions\when( 'esc_html' )->returnArg();
		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( null )
			->andReturn( $resolver );

		$result      = PPF_Site_Health::run_ppf_record_test();
		$description = $result['description'];

		$this->assertSame( 'critical', $result['status'] );
		$this->assertIsString( $description );
		$this->assertStringContainsString( 'not a valid PPF record', $description );
	}

	/**
	 * Ensure invalid record output explains when resolver lookup depth is exceeded.
	 *
	 * @return void
	 */
	public function test_run_ppf_record_test_reports_too_many_lookups_detail() {
		$resolver = new PPF_Record_Fake_Resolver();
		$resolver->set_txt_records( '_pingback.example.com', false );
		$resolver->set_txt_record_error( '_pingback.example.com', PPF_Record_Resolver::ERROR_TOO_MANY_LOOKUPS );

		Functions\when( '__' )->returnArg();
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'PPF\\Pingback\\wp_parse_url' )->justReturn( 'example.com' );
		Functions\when( 'esc_html' )->returnArg();
		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( null )
			->andReturn( $resolver );

		$result      = PPF_Site_Health::run_ppf_record_test();
		$description = $result['description'];

		$this->assertSame( 'critical', $result['status'] );
		$this->assertIsString( $description );
		$this->assertStringContainsString( 'lookup depth', $description );
		$this->assertStringContainsString( 'exceeded the resolver safety limit', $description );
	}

	/**
	 * Ensure invalid record output explains when multiple TXT records are returned.
	 *
	 * @return void
	 */
	public function test_run_ppf_record_test_reports_too_many_records_detail() {
		$resolver = new PPF_Record_Fake_Resolver();
		$resolver->set_txt_simulate_multiple_records( '_pingback.example.com' );

		Functions\when( '__' )->returnArg();
		Functions\when( 'home_url' )->justReturn( 'https://example.com' );
		Functions\when( 'PPF\\Pingback\\wp_parse_url' )->justReturn( 'example.com' );
		Functions\when( 'esc_html' )->returnArg();
		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( null )
			->andReturn( $resolver );

		$result      = PPF_Site_Health::run_ppf_record_test();
		$description = $result['description'];

		$this->assertSame( 'critical', $result['status'] );
		$this->assertIsString( $description );
		$this->assertStringContainsString( 'Multiple TXT records were returned', $description );
		$this->assertStringContainsString( 'requires exactly one authoritative TXT value', $description );
	}

	/**
	 * Ensure missing trusted proxies are harmless when no forwarded header is present.
	 *
	 * @return void
	 */
	public function test_run_ppf_trusted_proxies_test_reports_missing_configuration_without_forwarded_header() {
		Functions\when( '__' )->returnArg();

		$result = PPF_Site_Health::run_ppf_trusted_proxies_test();

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( PPF_Site_Health::TEST_PPF_TRUSTED_PROXIES, $result['test'] );
	}

	/**
	 * Ensure missing trusted proxies are critical when a forwarded header is present.
	 *
	 * @return void
	 */
	public function test_run_ppf_trusted_proxies_test_reports_missing_configuration_with_forwarded_header() {
		$this->withForwardedForHeader( '198.51.100.1' );
		Functions\when( '__' )->returnArg();

		$result = PPF_Site_Health::run_ppf_trusted_proxies_test();

		$this->assertSame( 'critical', $result['status'] );
	}

	/**
	 * Ensure invalid trusted proxy configuration is harmless when no forwarded header is present.
	 *
	 * @return void
	 */
	public function test_run_ppf_trusted_proxies_test_reports_invalid_configuration_without_forwarded_header() {
		$this->withTrustedProxies( '203.0.113.0/24' );
		Functions\when( '__' )->returnArg();

		$result = PPF_Site_Health::run_ppf_trusted_proxies_test();

		$this->assertSame( 'good', $result['status'] );
	}

	/**
	 * Ensure invalid trusted proxy configuration is critical when a forwarded header is present.
	 *
	 * @return void
	 */
	public function test_run_ppf_trusted_proxies_test_reports_invalid_configuration_with_forwarded_header() {
		$this->withTrustedProxies( '203.0.113.0/24' );
		$this->withForwardedForHeader( '198.51.100.1' );
		Functions\when( '__' )->returnArg();

		$result = PPF_Site_Health::run_ppf_trusted_proxies_test();

		$this->assertSame( 'critical', $result['status'] );
	}

	/**
	 * Ensure an empty trusted proxy list is treated as an explicit configuration.
	 *
	 * @return void
	 */
	public function test_run_ppf_trusted_proxies_test_reports_empty_configuration() {
		$this->withTrustedProxies( array() );
		Functions\when( '__' )->returnArg();

		$result      = PPF_Site_Health::run_ppf_trusted_proxies_test();
		$description = $result['description'];

		$this->assertSame( 'good', $result['status'] );
		$this->assertIsString( $description );
		$this->assertStringContainsString( 'empty array', $description );
	}

	/**
	 * Ensure a populated trusted proxy list is reported as configured.
	 *
	 * @return void
	 */
	public function test_run_ppf_trusted_proxies_test_reports_configured_proxies() {
		$this->withTrustedProxies( array( '203.0.113.0/24', '2001:db8::/32' ) );
		Functions\when( '__' )->returnArg();
		Functions\when( '_n' )->alias(
			function ( $single, $plural, $number ) {
				return 1 === $number ? $single : $plural;
			}
		);

		$result      = PPF_Site_Health::run_ppf_trusted_proxies_test();
		$description = $result['description'];

		$this->assertSame( 'good', $result['status'] );
		$this->assertIsString( $description );
		$this->assertStringContainsString( '2 configured proxy entries', $description );
	}

	/**
	 * Singular copy when exactly one string proxy entry is configured.
	 *
	 * @return void
	 */
	public function test_run_ppf_trusted_proxies_test_singular_entry_copy() {
		$this->withTrustedProxies( array( '203.0.113.0/24' ) );
		Functions\when( '__' )->returnArg();
		Functions\when( '_n' )->alias(
			static function ( $single, $plural, $number ) {
				return 1 === (int) $number ? $single : $plural;
			}
		);

		$result      = PPF_Site_Health::run_ppf_trusted_proxies_test();
		$description = $result['description'];

		$this->assertSame( 'good', $result['status'] );
		$this->assertIsString( $description );
		$this->assertStringContainsString( '1 configured proxy entry', $description );
	}

	/**
	 * Zero string proxy entries (non-strings only) should not throw.
	 *
	 * @return void
	 */
	public function test_run_ppf_trusted_proxies_test_non_string_entries_count_as_zero() {
		$this->withTrustedProxies( array( 99, true, array( 'x' ) ) );
		Functions\when( '__' )->returnArg();
		Functions\when( '_n' )->alias(
			static function ( $single, $plural, $number ) {
				return 1 === (int) $number ? $single : $plural;
			}
		);

		$result      = PPF_Site_Health::run_ppf_trusted_proxies_test();
		$description = $result['description'];

		$this->assertSame( 'good', $result['status'] );
		$this->assertIsString( $description );
		$this->assertStringContainsString( '0 configured proxy entries', $description );
	}

	/**
	 * Empty X-Forwarded-For must not be treated as a forwarded request.
	 *
	 * @return void
	 */
	public function test_run_ppf_trusted_proxies_test_empty_forwarded_header_is_ignored() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '';
		Functions\when( '__' )->returnArg();

		$result = PPF_Site_Health::run_ppf_trusted_proxies_test();

		$this->assertSame( 'good', $result['status'] );
	}
}
