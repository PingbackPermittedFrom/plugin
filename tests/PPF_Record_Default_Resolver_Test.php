<?php
/**
 * Tests for PPF record default resolver.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PPF\Pingback\PPF_Record_Default_Resolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

require_once dirname( __DIR__ ) . '/wp-includes/ppf/class-ppf-record-default-resolver.php';

/**
 * Coverage for default resolver behavior.
 *
 * @group ppf-record-default-resolver
 */
#[CoversClass( PPF_Record_Default_Resolver::class )]
class PPF_Record_Default_Resolver_Test extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Resolver instance.
	 *
	 * @var PPF_Record_Default_Resolver
	 */
	private $resolver;

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->resolver = new PPF_Record_Default_Resolver();
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
	 * Validate TXT records are served from cache.
	 *
	 * @return void
	 */
	public function test_get_txt_records_uses_cache() {
		$hostname  = 'example.com';
		$cache_key = 'ppf_txt_' . md5( $hostname );
		$cached    = 'v=ppf1 ip4:192.0.2.0/24';

		Functions\expect( 'PPF\\Pingback\\wp_cache_get' )
			->once()
			->with( $cache_key, 'ppf_pingback' )
			->andReturn( $cached );

		$result = $this->resolver->get_txt_records( $hostname );

		$this->assertSame( $cached, $result );
	}

	/**
	 * Validate TXT lookup returns null on DNS failure.
	 *
	 * @return void
	 */
	public function test_get_txt_records_returns_null_on_dns_failure() {
		$hostname       = 'example.com';
		$cache_key      = 'ppf_txt_' . md5( $hostname );
		$resolver_error = PPF_Record_Default_Resolver::ERROR_NONE;

		Functions\expect( 'PPF\\Pingback\\wp_cache_get' )
			->once()
			->with( $cache_key, 'ppf_pingback' )
			->andReturn( false );
		Functions\expect( 'PPF\\Pingback\\dns_get_record' )
			->once()
			->with( $hostname, DNS_TXT )
			->andReturn( false );

		$result = $this->resolver->get_txt_records( $hostname, $resolver_error );

		$this->assertNull( $result );
		$this->assertSame( PPF_Record_Default_Resolver::ERROR_UNKNOWN, $resolver_error );
	}

	/**
	 * Validate TXT lookup stops after max lookups.
	 *
	 * @return void
	 */
	public function test_get_txt_records_returns_false_after_max_lookups() {
		$hostname       = 'example.com';
		$cache_key      = 'ppf_txt_' . md5( $hostname );
		$resolver_error = PPF_Record_Default_Resolver::ERROR_NONE;

		Functions\expect( 'PPF\\Pingback\\wp_cache_get' )
			->times( 10 )
			->with( $cache_key, 'ppf_pingback' )
			->andReturn( 'v=ppf1 ip4:192.0.2.0/24' );

		for ( $i = 0; $i < 10; $i++ ) {
			$this->assertSame( 'v=ppf1 ip4:192.0.2.0/24', $this->resolver->get_txt_records( $hostname, $resolver_error ) );
			$this->assertSame( PPF_Record_Default_Resolver::ERROR_NONE, $resolver_error );
		}

		$this->assertFalse( $this->resolver->get_txt_records( $hostname, $resolver_error ) );
		$this->assertSame( PPF_Record_Default_Resolver::ERROR_TOO_MANY_LOOKUPS, $resolver_error );
	}

	/**
	 * Validate TXT lookup parses records and caches results.
	 *
	 * @return void
	 */
	public function test_get_txt_records_parses_entries_and_caches_result() {
		$hostname  = 'example.com';
		$cache_key = 'ppf_txt_' . md5( $hostname );
		$records   = array( array( 'entries' => array( 'v=ppf1 ', 'ip4:198.51.100.0/24' ) ) );
		$expected  = 'v=ppf1 ip4:198.51.100.0/24';

		Functions\expect( 'PPF\\Pingback\\wp_cache_get' )
			->once()
			->with( $cache_key, 'ppf_pingback' )
			->andReturn( false );
		Functions\expect( 'PPF\\Pingback\\dns_get_record' )
			->once()
			->with( $hostname, DNS_TXT )
			->andReturn( $records );
		Functions\expect( 'PPF\\Pingback\\wp_cache_set' )
			->once()
			->with( $cache_key, $expected, 'ppf_pingback', 3600 )
			->andReturn( true );

		$result = $this->resolver->get_txt_records( $hostname );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Validate TXT lookup uses the txt-shaped row from dns_get_record (not entries) and caches.
	 *
	 * @return void
	 */
	public function test_get_txt_records_parses_txt_key_and_caches_result() {
		$hostname  = 'example.com';
		$cache_key = 'ppf_txt_' . md5( $hostname );
		$records   = array( array( 'txt' => 'v=ppf1 ip4:198.51.100.0/24' ) );
		$expected  = 'v=ppf1 ip4:198.51.100.0/24';

		Functions\expect( 'PPF\\Pingback\\wp_cache_get' )
			->once()
			->with( $cache_key, 'ppf_pingback' )
			->andReturn( false );
		Functions\expect( 'PPF\\Pingback\\dns_get_record' )
			->once()
			->with( $hostname, DNS_TXT )
			->andReturn( $records );
		Functions\expect( 'PPF\\Pingback\\wp_cache_set' )
			->once()
			->with( $cache_key, $expected, 'ppf_pingback', 3600 )
			->andReturn( true );

		$result = $this->resolver->get_txt_records( $hostname );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Validate TXT lookup returns false when multiple records are returned.
	 *
	 * @return void
	 */
	public function test_get_txt_records_returns_false_when_multiple_records_returned() {
		$hostname       = 'example.com';
		$cache_key      = 'ppf_txt_' . md5( $hostname );
		$resolver_error = PPF_Record_Default_Resolver::ERROR_NONE;
		$records        = array(
			array( 'txt' => 'v=ppf1 ip4:192.0.2.0/24' ),
			array( 'txt' => 'v=ppf1 ip4:198.51.100.0/24' ),
		);

		Functions\expect( 'PPF\\Pingback\\wp_cache_get' )
			->once()
			->with( $cache_key, 'ppf_pingback' )
			->andReturn( false );
		Functions\expect( 'PPF\\Pingback\\dns_get_record' )
			->once()
			->with( $hostname, DNS_TXT )
			->andReturn( $records );
		Functions\expect( 'PPF\\Pingback\\wp_cache_set' )
			->never();

		$result = $this->resolver->get_txt_records( $hostname, $resolver_error );

		$this->assertFalse( $result );
		$this->assertSame( PPF_Record_Default_Resolver::ERROR_TOO_MANY_RECORDS, $resolver_error );
	}

	/**
	 * Validate TXT lookup returns null when DNS returns no TXT rows.
	 *
	 * @return void
	 */
	public function test_get_txt_records_returns_null_when_dns_returns_no_txt_rows() {
		$hostname       = 'example.com';
		$cache_key      = 'ppf_txt_' . md5( $hostname );
		$resolver_error = PPF_Record_Default_Resolver::ERROR_NONE;

		Functions\expect( 'PPF\\Pingback\\wp_cache_get' )
			->once()
			->with( $cache_key, 'ppf_pingback' )
			->andReturn( false );
		Functions\expect( 'PPF\\Pingback\\dns_get_record' )
			->once()
			->with( $hostname, DNS_TXT )
			->andReturn( array() );
		Functions\expect( 'PPF\\Pingback\\wp_cache_set' )
			->never();

		$result = $this->resolver->get_txt_records( $hostname, $resolver_error );

		$this->assertNull( $result );
		$this->assertSame( PPF_Record_Default_Resolver::ERROR_NO_RECORDS, $resolver_error );
	}

	/**
	 * Validate TXT lookup returns null when DNS record is invalid.
	 *
	 * @return void
	 */
	public function test_get_txt_records_returns_null_on_invalid_record() {
		$hostname       = 'example.com';
		$cache_key      = 'ppf_txt_' . md5( $hostname );
		$resolver_error = PPF_Record_Default_Resolver::ERROR_NONE;
		$records        = array(
			array( 'foo' => 'bar' ),
		);

		Functions\expect( 'PPF\\Pingback\\wp_cache_get' )
			->once()
			->with( $cache_key, 'ppf_pingback' )
			->andReturn( false );
		Functions\expect( 'PPF\\Pingback\\dns_get_record' )
			->once()
			->with( $hostname, DNS_TXT )
			->andReturn( $records );
		Functions\expect( 'PPF\\Pingback\\wp_cache_set' )
			->never();

		$result = $this->resolver->get_txt_records( $hostname, $resolver_error );

		$this->assertNull( $result );
		$this->assertSame( PPF_Record_Default_Resolver::ERROR_INVALID_RECORD, $resolver_error );
	}

	/**
	 * Validate host IPs are served from cache.
	 *
	 * @return void
	 */
	public function test_get_host_ips_uses_cache() {
		$hostname  = 'example.com';
		$cache_key = 'ppf_ip_' . md5( $hostname );
		$cached    = array( '192.0.2.1', '2001:db8::1' );

		Functions\expect( 'PPF\\Pingback\\wp_cache_get' )
			->once()
			->with( $cache_key, 'ppf_pingback' )
			->andReturn( $cached );

		$result = $this->resolver->get_host_ips( $hostname );

		$this->assertSame( $cached, $result );
	}

	/**
	 * Validate host IP lookup returns null on DNS failure.
	 *
	 * @return void
	 */
	public function test_get_host_ips_returns_null_on_dns_failure() {
		$hostname  = 'example.com';
		$cache_key = 'ppf_ip_' . md5( $hostname );

		Functions\expect( 'PPF\\Pingback\\wp_cache_get' )
			->once()
			->with( $cache_key, 'ppf_pingback' )
			->andReturn( false );
		Functions\expect( 'PPF\\Pingback\\dns_get_record' )
			->once()
			->with( $hostname, DNS_A | DNS_AAAA )
			->andReturn( false );

		$result = $this->resolver->get_host_ips( $hostname );

		$this->assertNull( $result );
	}

	/**
	 * Validate host IP lookup returns false for empty hostname.
	 *
	 * @return void
	 */
	public function test_get_host_ips_returns_false_on_empty_hostname() {
		$this->assertFalse( $this->resolver->get_host_ips( '' ) );
	}

	/**
	 * Validate host IP lookup parses IPv4 and IPv6 records.
	 *
	 * @return void
	 */
	public function test_get_host_ips_parses_ip_and_ipv6() {
		$hostname  = 'example.com';
		$cache_key = 'ppf_ip_' . md5( $hostname );
		$records   = array(
			array( 'ip' => '192.0.2.1' ),
			array( 'ipv6' => '2001:db8::1' ),
			array( 'foo' => 'bar' ),
		);
		$expected  = array( '192.0.2.1', '2001:db8::1' );

		Functions\expect( 'PPF\\Pingback\\wp_cache_get' )
			->once()
			->with( $cache_key, 'ppf_pingback' )
			->andReturn( false );
		Functions\expect( 'PPF\\Pingback\\dns_get_record' )
			->once()
			->with( $hostname, DNS_A | DNS_AAAA )
			->andReturn( $records );
		Functions\expect( 'PPF\\Pingback\\wp_cache_set' )
			->once()
			->with( $cache_key, $expected, 'ppf_pingback', 3600 )
			->andReturn( true );

		$result = $this->resolver->get_host_ips( $hostname );

		$this->assertSame( $expected, $result );
	}
}
