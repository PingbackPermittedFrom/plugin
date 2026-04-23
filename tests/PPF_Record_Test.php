<?php
/**
 * Tests for PPF record helper.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PPF\Pingback\PPF_Record;
use PPF\Pingback\PPF_Record_Default_Resolver;
use PPF\Pingback\PPF_Record_Resolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

require_once dirname( __DIR__ ) . '/wp-includes/ppf/class-ppf-record.php';
require_once __DIR__ . '/class-ppf-record-fake-resolver.php';
require_once __DIR__ . '/class-ppf-record-test-stub.php';

/**
 * Coverage for record helper behavior.
 *
 * @group ppf-record
 */
#[CoversClass( PPF_Record::class )]
#[UsesClass( PPF_Record_Default_Resolver::class )]
class PPF_Record_Test extends TestCase {
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
	 * Clean up Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Validate standard resolver is used when no resolver is provided.
	 *
	 * @return void
	 */
	public function test_standard_resolver_is_used_when_no_resolver_is_provided() {
		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( null )
			->andReturn( null );

		$record = new PPF_Record();
		$this->assertInstanceOf( PPF_Record_Default_Resolver::class, $record->get_resolver() );
	}

	/**
	 * Validate resolver filter can override resolver instance.
	 *
	 * @return void
	 */
	public function test_resolver_filter_can_override_resolver_instance() {
		$override_resolver = new PPF_Record_Fake_Resolver();
		$override_resolver->set_txt_records( '_pingback.example.com', 'v=ppf1 ip4:192.0.2.0/24' );

		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( null )
			->andReturn( $override_resolver );

		$record = new PPF_Record();
		$status = $record->resolve( 'example.com' );

		$this->assertSame( PPF_Record::STATUS_OK, $status );
		$this->assertSame( array( 'ip4:192.0.2.0/24' ), $record->get_tokens() );
	}

	/**
	 * Validate tokens and record are empty before resolve.
	 *
	 * @return void
	 */
	public function test_getters_return_empty_before_resolve() {
		$resolver = new PPF_Record_Fake_Resolver();
		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( $resolver )
			->andReturn( $resolver );

		$record = new PPF_Record( $resolver );

		$this->assertSame( array(), $record->get_tokens() );
		$this->assertNull( $record->get_record() );
	}

	/**
	 * Validate resolver is used when provided explicitly.
	 *
	 * @return void
	 */
	public function test_constructor_uses_explicit_resolver() {
		$resolver = new PPF_Record_Fake_Resolver();
		$resolver->set_txt_records( '_pingback.example.com', 'v=ppf1 ip6:2001:db8::/32' );

		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( $resolver )
			->andReturn( $resolver );

		$record = new PPF_Record( $resolver );
		$status = $record->resolve( 'example.com' );

		$this->assertSame( PPF_Record::STATUS_OK, $status );
		$this->assertSame( array( 'ip6:2001:db8::/32' ), $record->get_tokens() );
	}

	/**
	 * Validate resolve returns invalid for empty hostnames.
	 *
	 * @return void
	 */
	public function test_resolve_returns_invalid_for_empty_host() {
		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( null )
			->andReturn( new PPF_Record_Fake_Resolver() );

		$record = new PPF_Record();
		$status = $record->resolve( '   ' );

		$this->assertSame( PPF_Record::STATUS_INVALID, $status );
		$this->assertSame( array(), $record->get_tokens() );
	}

	/**
	 * Validate resolve returns no_record when TXT records are empty.
	 *
	 * @return void
	 */
	public function test_resolve_returns_no_record_when_empty_txts() {
		$resolver = new PPF_Record_Fake_Resolver();
		$resolver->set_txt_record_error( '_pingback.example.com', PPF_Record_Resolver::ERROR_NO_RECORDS );

		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( null )
			->andReturn( $resolver );

		$record = new PPF_Record();
		$status = $record->resolve( 'example.com' );

		$this->assertSame( PPF_Record::STATUS_NO_RECORD, $status );
		$this->assertSame( PPF_Record_Resolver::ERROR_NO_RECORDS, $record->get_resolver_error() );
	}

	/**
	 * Validate resolve returns invalid when no PPF1 record exists.
	 *
	 * @return void
	 */
	public function test_resolve_returns_invalid_for_non_ppf_record() {
		$resolver = new PPF_Record_Fake_Resolver();
		$resolver->set_txt_records( '_pingback.example.com', 'v=ppf2 ip4:192.0.2.0/24' );

		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( null )
			->andReturn( $resolver );

		$record = new PPF_Record();
		$status = $record->resolve( 'example.com' );

		$this->assertSame( PPF_Record::STATUS_INVALID, $status );
		$this->assertNull( $record->get_record() );
	}

	/**
	 * Validate resolve returns invalid when lookup count exceeds 10.
	 *
	 * @return void
	 */
	public function test_resolve_returns_invalid_when_lookup_count_exceeds_10() {
		Functions\expect( 'PPF\\Pingback\\wp_cache_get' )
			->times( 10 )
			->andReturn( 'v=ppf1 ip4:192.0.2.0/24' );

		$record = new PPF_Record();
		$count  = 0;
		do {
			$status = $record->resolve( 'example.com' );
			++$count;
		} while ( $count < 100 && PPF_Record::STATUS_INVALID !== $status );

		$this->assertSame( PPF_Record::STATUS_INVALID, $status );
		$this->assertSame( 11, $count );
		$this->assertSame( PPF_Record_Resolver::ERROR_TOO_MANY_LOOKUPS, $record->get_resolver_error() );
	}

	/**
	 * Validate resolve reports invalid and preserves ERROR_TOO_MANY_RECORDS when TXT has multiple RRs.
	 *
	 * @return void
	 */
	public function test_resolve_returns_invalid_when_multiple_txt_records() {
		$resolver = new PPF_Record_Fake_Resolver();
		$resolver->set_txt_simulate_multiple_records( '_pingback.example.com' );

		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( null )
			->andReturn( $resolver );

		$record = new PPF_Record();
		$status = $record->resolve( 'example.com' );

		$this->assertSame( PPF_Record::STATUS_INVALID, $status );
		$this->assertSame( PPF_Record_Resolver::ERROR_TOO_MANY_RECORDS, $record->get_resolver_error() );
		$this->assertSame( array(), $record->get_tokens() );
		$this->assertNull( $record->get_record() );
	}

	/**
	 * Validate resolve returns invalid when resolver fails.
	 *
	 * @return void
	 */
	public function test_resolve_returns_no_record_when_resolver_fails() {
		$resolver = new PPF_Record_Fake_Resolver();
		$resolver->set_txt_records( '_pingback.example.com', null );

		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( null )
			->andReturn( $resolver );

		$record = new PPF_Record();
		$status = $record->resolve( 'example.com' );

		$this->assertSame( PPF_Record::STATUS_NO_RECORD, $status );
	}

	/**
	 * Validate resolve normalizes hostnames and tokens.
	 *
	 * @return void
	 */
	public function test_resolve_normalizes_host_and_tokens() {
		$resolver = new PPF_Record_Fake_Resolver();
		$resolver->set_txt_records( '_pingback.example.com', 'v=ppf1   IP4:192.0.2.0/24   ip6:2001:DB8::/32' );

		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( null )
			->andReturn( $resolver );

		$record = new PPF_Record();
		$status = $record->resolve( 'Example.com.' );

		$this->assertSame( PPF_Record::STATUS_OK, $status );
		$this->assertSame(
			array( 'ip4:192.0.2.0/24', 'ip6:2001:db8::/32' ),
			$record->get_tokens()
		);
		$this->assertSame( 'v=ppf1   ip4:192.0.2.0/24   ip6:2001:db8::/32', $record->get_record() );
	}

	/**
	 * Validate host IPs are proxied to resolver.
	 *
	 * @return void
	 */
	public function test_get_host_ips_delegates_to_resolver() {
		$resolver = new PPF_Record_Fake_Resolver();
		$resolver->set_host_ips( 'example.com', array( '192.0.2.1' ) );

		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( $resolver )
			->andReturn( $resolver );

		$record = new PPF_Record( $resolver );
		$this->assertSame( array( '192.0.2.1' ), $record->get_host_ips( 'example.com' ) );
	}

	/**
	 * Validate normalize_host behavior.
	 *
	 * @return void
	 */
	public function test_normalize_host_normalizes_input() {
		$resolver = new PPF_Record_Fake_Resolver();
		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( $resolver )
			->andReturn( $resolver );

		$record = new PPF_Record_Test_Stub( $resolver );

		$this->assertSame( 'example.com', $record->normalize_host_public( 'Example.COM.' ) );
		$this->assertSame( 'example.com', $record->normalize_host_public( '  example.com  ' ) );
		$this->assertSame( '', $record->normalize_host_public( '   ' ) );
	}
}
