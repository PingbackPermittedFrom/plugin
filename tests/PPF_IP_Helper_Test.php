<?php
/**
 * Tests for PPF IP helper.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PPF\Pingback\PPF_IP_Helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

require_once dirname( __DIR__ ) . '/wp-includes/ppf/class-ppf-ip-helper.php';
require_once __DIR__ . '/class-ppf-ip-helper-test-stub.php';

/**
 * Coverage for IP helper.
 *
 * @group ppf-ip-helper
 */
#[CoversClass( PPF_IP_Helper::class )]
class PPF_IP_Helper_Test extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Helper instance.
	 *
	 * @var PPF_IP_Helper_Test_Stub
	 */
	private $helper;

	/**
	 * Set up helper.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR'] );
		$this->helper = new PPF_IP_Helper_Test_Stub();
	}

	/**
	 * Reset server globals after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR'] );
		Monkey\tearDown();
		parent::tearDown();
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
	 * Validate IPv4 matching behavior.
	 *
	 * @param string $ip Sender IP address.
	 * @param string $range Range string.
	 * @param bool   $expected Expected match result.
	 * @return void
	 */
	#[DataProvider( 'ipv4_provider' )]
	public function test_matches_ipv4( $ip, $range, $expected ) {
		$result = $this->helper->matches_ipv4( $ip, $range );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Provide IPv4 match cases.
	 *
	 * @return array<string, array{0:string,1:string,2:bool}>
	 */
	public static function ipv4_provider() {
		return array(
			'ip4 exact match'       => array( '192.0.2.1', '192.0.2.1', true ),
			'ip4 exact miss'        => array( '192.0.2.2', '192.0.2.1', false ),
			'ip4 cidr match'        => array( '192.0.2.100', '192.0.2.0/24', true ),
			'ip4 cidr boundary 0'   => array( '192.0.2.0', '192.0.2.0/24', true ),
			'ip4 cidr boundary 255' => array( '192.0.2.255', '192.0.2.0/24', true ),
			'ip4 cidr miss'         => array( '192.0.3.1', '192.0.2.0/24', false ),
			'ip4 /32 single host'   => array( '192.0.2.1', '192.0.2.1/32', true ),
			'ip4 /32 miss'          => array( '192.0.2.2', '192.0.2.1/32', false ),
			'ip4 /0 matches all'    => array( '203.0.113.5', '0.0.0.0/0', true ),
			'ip4 invalid ip'        => array( 'not-an-ip', '192.0.2.0/24', false ),
			'ip4 invalid subnet'    => array( '192.0.2.1', '300.0.0.0/24', false ),
			'ip4 invalid mask'      => array( '192.0.2.1', '192.0.2.0/33', false ),
			'ip4 negative mask'     => array( '192.0.2.1', '192.0.2.0/-1', false ),
			'ip4 ipv6 sender'       => array( '2001:db8::1', '192.0.2.0/24', false ),
		);
	}

	/**
	 * Validate IPv6 matching behavior.
	 *
	 * @param string $ip Sender IP address.
	 * @param string $range Range string.
	 * @param bool   $expected Expected match result.
	 * @return void
	 */
	#[DataProvider( 'ipv6_provider' )]
	public function test_matches_ipv6( $ip, $range, $expected ) {
		$result = $this->helper->matches_ipv6( $ip, $range );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Provide IPv6 match cases.
	 *
	 * @return array<string, array{0:string,1:string,2:bool}>
	 */
	public static function ipv6_provider() {
		return array(
			'ip6 exact match'      => array( '2001:db8::1', '2001:db8::1', true ),
			'ip6 exact miss'       => array( '2001:db8::2', '2001:db8::1', false ),
			'ip6 cidr match'       => array( '2001:db8::abcd', '2001:db8::/64', true ),
			'ip6 cidr boundary 0'  => array( '2001:db8::', '2001:db8::/64', true ),
			'ip6 cidr boundary f'  => array( '2001:db8::ffff', '2001:db8::/64', true ),
			'ip6 cidr miss'        => array( '2001:db9::1', '2001:db8::/64', false ),
			'ip6 /128 single host' => array( '2001:db8::1', '2001:db8::1/128', true ),
			'ip6 /128 miss'        => array( '2001:db8::2', '2001:db8::1/128', false ),
			'ip6 /0 matches all'   => array( '2001:db8::beef', '::/0', true ),
			'ip6 invalid ip'       => array( 'not-an-ip', '2001:db8::/64', false ),
			'ip6 invalid subnet'   => array( '2001:db8::1', 'invalid::/64', false ),
			'ip6 invalid mask'     => array( '2001:db8::1', '2001:db8::/129', false ),
			'ip6 negative mask'    => array( '2001:db8::1', '2001:db8::/-1', false ),
			'ip6 ipv4 sender'      => array( '192.0.2.1', '2001:db8::/64', false ),
			// Non-byte-aligned mask: remainder branch (mask % 8 !== 0).
			'ip6 /65 match'        => array( '2001:db8::1', '2001:db8::/65', true ),
			'ip6 /65 miss'         => array( '2001:db8::8000:0:0:0', '2001:db8::/65', false ),
			'ip6 /1 match'         => array( '::1', '::/1', true ),
			'ip6 /1 miss'          => array( '8000::1', '::/1', false ),
		);
	}

	/**
	 * Ensure filter-provided remote IP is honored.
	 *
	 * @return void
	 */
	public function test_get_remote_ip_uses_filter() {
		Filters\expectApplied( 'ppf_pingback_remote_ip' )
			->once()
			->with( false, 'http://example.com', 'http://example.org', array() )
			->andReturn( '198.51.100.5' );

		$result = $this->helper->get_remote_ip( 'http://example.com', 'http://example.org', array() );

		$this->assertSame( '198.51.100.5', $result );
	}

	/**
	 * Ensure default filter behavior returns first argument.
	 *
	 * @return void
	 */
	public function test_get_remote_ip_default_filter_is_false() {
		Filters\expectApplied( 'ppf_pingback_remote_ip' )
			->once()
			->andReturnFirstArg();

		$result = $this->helper->get_remote_ip( 'http://example.com', 'http://example.org', array() );

		$this->assertFalse( $result );
	}

	/**
	 * Ensure invalid filter value falls back to REMOTE_ADDR.
	 *
	 * @return void
	 */
	public function test_get_remote_ip_invalid_filter_falls_back() {
		Filters\expectApplied( 'ppf_pingback_remote_ip' )
			->once()
			->andReturn( 'not-an-ip' );
		Functions\expect( 'PPF\\Pingback\\_doing_it_wrong' )
			->once();
			Functions\expect( 'wp_unslash' )
			->once()
			->andReturnFirstArg();
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';

		$result = $this->helper->get_remote_ip( 'http://example.com', 'http://example.org', array() );

		$this->assertSame( '203.0.113.7', $result );
	}

	/**
	 * Ensure HTTP_X_FORWARDED_FOR uses the first IP when the proxy is trusted.
	 *
	 * @return void
	 */
	public function test_get_remote_ip_uses_forwarded_for_first_ip_from_trusted_proxy() {
		$this->withTrustedProxies( array( '203.0.113.0/24' ) );

		Filters\expectApplied( 'ppf_pingback_remote_ip' )
			->once()
			->andReturnFirstArg();
		Functions\expect( 'wp_unslash' )
			->twice()
			->andReturnFirstArg();
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 203.0.113.9';
		$_SERVER['REMOTE_ADDR']          = '203.0.113.7';

		$result = $this->helper->get_remote_ip( 'http://example.com', 'http://example.org', array() );

		$this->assertSame( '198.51.100.1', $result );
	}

	/**
	 * Ensure untrusted proxies do not influence the resolved client IP.
	 *
	 * @return void
	 */
	public function test_get_remote_ip_ignores_forwarded_for_from_untrusted_proxy() {
		$this->withTrustedProxies( array( '198.51.100.0/24' ) );

		Filters\expectApplied( 'ppf_pingback_remote_ip' )
			->once()
			->andReturnFirstArg();
		Functions\expect( 'wp_unslash' )
			->once()
			->andReturnFirstArg();
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 203.0.113.9';
		$_SERVER['REMOTE_ADDR']          = '203.0.113.7';

		$result = $this->helper->get_remote_ip( 'http://example.com', 'http://example.org', array() );

		$this->assertSame( '203.0.113.7', $result );
	}

	/**
	 * Ensure invalid forwarded IP from a trusted proxy is rejected.
	 *
	 * @return void
	 */
	public function test_get_remote_ip_invalid_forwarded_for_is_false() {
		$this->withTrustedProxies( array( '203.0.113.0/24' ) );

		Filters\expectApplied( 'ppf_pingback_remote_ip' )
			->once()
			->andReturnFirstArg();
		Functions\expect( 'wp_unslash' )
			->twice()
			->andReturnFirstArg();
		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';
		$_SERVER['REMOTE_ADDR']          = '203.0.113.7';

		$result = $this->helper->get_remote_ip( 'http://example.com', 'http://example.org', array() );

		$this->assertFalse( $result );
	}

	/**
	 * Ensure REMOTE_ADDR is used when forwarded header missing.
	 *
	 * @return void
	 */
	public function test_get_remote_ip_uses_remote_addr() {
		Filters\expectApplied( 'ppf_pingback_remote_ip' )
			->once()
			->andReturnFirstArg();
		Functions\expect( 'wp_unslash' )
			->once()
			->andReturnFirstArg();
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';

		$result = $this->helper->get_remote_ip( 'http://example.com', 'http://example.org', array() );

		$this->assertSame( '203.0.113.7', $result );
	}

	/**
	 * Ensure invalid trusted proxy configuration is ignored.
	 *
	 * @return void
	 */
	public function test_get_remote_ip_ignores_forwarded_for_when_trusted_proxies_is_invalid() {
		$this->withTrustedProxies( '203.0.113.0/24' );

		Filters\expectApplied( 'ppf_pingback_remote_ip' )
			->once()
			->andReturnFirstArg();
		Functions\expect( 'PPF\\Pingback\\_doing_it_wrong' )
			->once();
		Functions\expect( 'wp_unslash' )
			->once()
			->andReturnFirstArg();
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 203.0.113.9';
		$_SERVER['REMOTE_ADDR']          = '203.0.113.7';

		$result = $this->helper->get_remote_ip( 'http://example.com', 'http://example.org', array() );

		$this->assertSame( '203.0.113.7', $result );
	}

	/**
	 * Ensure invalid direct peer IPs are never treated as trusted proxies.
	 *
	 * @return void
	 */
	public function test_is_trusted_proxy_returns_false_for_invalid_remote_addr() {
		$this->withTrustedProxies( array( '203.0.113.0/24' ) );

		$this->assertFalse( $this->helper->call_is_trusted_proxy( 'not-an-ip' ) );
	}

	/**
	 * Ensure missing trusted proxy configuration returns false.
	 *
	 * @return void
	 */
	public function test_is_trusted_proxy_returns_false_when_constant_is_missing() {
		$this->assertFalse( $this->helper->call_is_trusted_proxy( '203.0.113.7' ) );
	}

	/**
	 * Ensure non-string proxy entries are skipped when checking trusted proxies.
	 *
	 * @return void
	 */
	public function test_is_trusted_proxy_skips_invalid_entries_and_matches_valid_range() {
		$this->withTrustedProxies( array( null, '', '203.0.113.0/24' ) );

		$this->assertTrue( $this->helper->call_is_trusted_proxy( '203.0.113.7' ) );
	}
}
