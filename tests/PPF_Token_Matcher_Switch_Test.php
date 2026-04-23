<?php
/**
 * Tests for switch-based token matcher.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use Brain\Monkey;
use Brain\Monkey\Filters;
use PPF\Pingback\PPF_IP_Helper;
use PPF\Pingback\PPF_Record;
use PPF\Pingback\PPF_Record_Resolver;
use PPF\Pingback\PPF_Result;
use PPF\Pingback\PPF_Token_Matcher_Switch;
use PPF\Pingback\Tests\PPF_Token_Matcher_Switch_Test_Stub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

require_once dirname( __DIR__ ) . '/wp-includes/ppf/class-ppf-ip-helper.php';
require_once dirname( __DIR__ ) . '/wp-includes/ppf/class-ppf-record.php';
require_once dirname( __DIR__ ) . '/wp-includes/ppf/class-ppf-token-matcher-switch.php';
require_once __DIR__ . '/class-ppf-record-fake-resolver.php';
require_once __DIR__ . '/class-ppf-token-matcher-switch-test-stub.php';

/**
 * Coverage for switch-based matcher.
 *
 * @group ppf-token-matcher-switch
 */
#[CoversClass( PPF_Token_Matcher_Switch::class )]
#[UsesClass( PPF_Record::class )]
#[UsesClass( PPF_IP_Helper::class )]
class PPF_Token_Matcher_Switch_Test extends TestCase {
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
	 * Create matcher and resolver.
	 *
	 * @return array{0:PPF_Token_Matcher_Switch_Test_Stub,1:PPF_Record_Fake_Resolver}
	 */
	private function create_matcher() {
		$resolver = new PPF_Record_Fake_Resolver();
		Filters\expectApplied( 'ppf_authorization_resolver' )
			->once()
			->with( $resolver )
			->andReturn( $resolver );
		$record  = new PPF_Record( $resolver );
		$matcher = new PPF_Token_Matcher_Switch_Test_Stub( $record, new PPF_IP_Helper() );
		return array( $matcher, $resolver );
	}

	/**
	 * Validate match_token handles empty tokens.
	 *
	 * @return void
	 */
	public function test_match_token_empty_returns_null() {
		list( $matcher ) = $this->create_matcher();
		$this->assertNull( $matcher->match_token_public( '   ', '192.0.2.1', 'example.com' ) );
	}

	/**
	 * Validate match_token returns false for none.
	 *
	 * @return void
	 */
	public function test_match_token_none_returns_false() {
		list( $matcher ) = $this->create_matcher();
		$this->assertFalse( $matcher->match_token_public( 'none', '192.0.2.1', 'example.com' ) );
	}

	/**
	 * Validate match_token delegates to "a".
	 *
	 * @return void
	 */
	public function test_match_token_a_delegates() {
		list( $matcher, $resolver ) = $this->create_matcher();
		$resolver->set_host_ips( 'example.com', array( '203.0.113.10' ) );

		$this->assertTrue( $matcher->match_token_public( 'a', '203.0.113.10', 'example.com' ) );
		$this->assertNull( $matcher->match_token_public( 'a', '203.0.113.11', 'example.com' ) );
	}

	/**
	 * Validate match_token delegates to ip6.
	 *
	 * @return void
	 */
	public function test_match_token_ip6_delegates() {
		list( $matcher ) = $this->create_matcher();

		$this->assertTrue( $matcher->match_token_public( 'ip6:2001:db8::/64', '2001:db8::1', 'example.com' ) );
		$this->assertNull( $matcher->match_token_public( 'ip6:2001:db8::/64', '2001:db9::1', 'example.com' ) );
	}

	/**
	 * Validate match_token delegates to include.
	 *
	 * @return void
	 */
	public function test_match_token_include_delegates() {
		list( $matcher ) = $this->create_matcher();
		$matcher->set_forced_evaluate_record( true, PPF_Record::STATUS_OK );

		$this->assertTrue( $matcher->match_token_public( 'include:include.example', '192.0.2.1', 'example.com' ) );
	}

	/**
	 * Validate match_token returns null for unknown type.
	 *
	 * @return void
	 */
	public function test_match_token_unknown_returns_null() {
		list( $matcher ) = $this->create_matcher();
		$this->assertNull( $matcher->match_token_public( 'mx:example.com', '192.0.2.1', 'example.com' ) );
	}

	/**
	 * Validate match_a returns true for matching host IP.
	 *
	 * @return void
	 */
	public function test_match_a_matches_host_ip() {
		list( $matcher, $resolver ) = $this->create_matcher();
		$resolver->set_host_ips( 'example.com', array( '203.0.113.10' ) );

		$this->assertTrue( $matcher->match_a_public( 'example.com', null, '203.0.113.10' ) );
		$this->assertNull( $matcher->match_a_public( 'example.com', null, '203.0.113.11' ) );
	}

	/**
	 * Validate match_a uses override host when present.
	 *
	 * @return void
	 */
	public function test_match_a_uses_override_host() {
		list( $matcher, $resolver ) = $this->create_matcher();
		$resolver->set_host_ips( 'origin.example', array( '198.51.100.7' ) );

		$this->assertTrue( $matcher->match_a_public( 'example.com', 'origin.example', '198.51.100.7' ) );
		$this->assertNull( $matcher->match_a_public( 'example.com', 'origin.example', '198.51.100.8' ) );
	}

	/**
	 * Validate match_ip4 behavior.
	 *
	 * @return void
	 */
	public function test_match_ip4_behavior() {
		list( $matcher ) = $this->create_matcher();
		$this->assertTrue( $matcher->match_ip4_public( '192.0.2.0/24', '192.0.2.5' ) );
		$this->assertNull( $matcher->match_ip4_public( '192.0.2.0/24', '192.0.3.5' ) );
		$this->assertNull( $matcher->match_ip4_public( null, '192.0.2.5' ) );
	}

	/**
	 * Validate match_ip6 behavior.
	 *
	 * @return void
	 */
	public function test_match_ip6_behavior() {
		list( $matcher ) = $this->create_matcher();
		$this->assertTrue( $matcher->match_ip6_public( '2001:db8::/64', '2001:db8::1' ) );
		$this->assertNull( $matcher->match_ip6_public( '2001:db8::/64', '2001:db9::1' ) );
		$this->assertNull( $matcher->match_ip6_public( null, '2001:db8::1' ) );
	}

	/**
	 * Validate match_tokens returns first boolean.
	 *
	 * @return void
	 */
	public function test_match_tokens_returns_first_boolean() {
		list( $matcher ) = $this->create_matcher();
		$tokens          = array( 'ip4:192.0.2.0/24', 'none' );

		$this->assertTrue( $matcher->match_tokens_public( $tokens, '192.0.2.5', 'example.com', 0 ) );
	}

	/**
	 * Validate match_tokens returns null when no token matches.
	 *
	 * @return void
	 */
	public function test_match_tokens_returns_null_when_no_match() {
		list( $matcher ) = $this->create_matcher();
		$tokens          = array( 'mx:example.com', 'ip4:' );

		$this->assertNull( $matcher->match_tokens_public( $tokens, '192.0.2.5', 'example.com', 0 ) );
	}

	/**
	 * Validate evaluate_record returns match for matching tokens.
	 *
	 * @return void
	 */
	public function test_evaluate_record_matches_tokens() {
		list( $matcher, $resolver ) = $this->create_matcher();
		$resolver->set_txt_records( '_pingback.example.com', 'v=ppf1 ip4:192.0.2.0/24' );

		$resolve_result = null;
		$this->assertTrue( $matcher->evaluate_record_public( 'example.com', '192.0.2.5', 1, $resolve_result ) );
		$this->assertSame( PPF_Record::STATUS_OK, $resolve_result );
	}

	/**
	 * Validate evaluate_record returns null on no record.
	 *
	 * @return void
	 */
	public function test_evaluate_record_returns_null_on_no_record() {
		list( $matcher, $resolver ) = $this->create_matcher();
		$resolver->set_txt_record_error( '_pingback.example.com', PPF_Record_Resolver::ERROR_NO_RECORDS );

		$resolve_result = null;
		$this->assertNull( $matcher->evaluate_record_public( 'example.com', '192.0.2.5', 1, $resolve_result ) );
		$this->assertSame( PPF_Record::STATUS_NO_RECORD, $resolve_result );
	}

	/**
	 * Validate evaluate_record returns null on invalid record.
	 *
	 * @return void
	 */
	public function test_evaluate_record_returns_null_on_invalid_record() {
		list( $matcher, $resolver ) = $this->create_matcher();
		$resolver->set_txt_records( '_pingback.example.com', 'v=ppf2 ip4:198.51.100.0/24' );

		$resolve_result = null;
		$this->assertNull( $matcher->evaluate_record_public( 'example.com', '192.0.2.5', 1, $resolve_result ) );
		$this->assertSame( PPF_Record::STATUS_INVALID, $resolve_result );
	}

	/**
	 * Validate evaluate_record returns null on null host.
	 *
	 * @return void
	 */
	public function test_evaluate_record_returns_null_on_null_host() {
		list( $matcher ) = $this->create_matcher();

		$resolve_result = null;
		$this->assertFalse( $matcher->evaluate_record_public( null, '192.0.2.5', 1, $resolve_result ) );
	}

	/**
	 * Validate evaluate_record returns false when too deep.
	 *
	 * @return void
	 */
	public function test_evaluate_record_respects_depth() {
		list( $matcher, $resolver ) = $this->create_matcher();
		$resolver->set_txt_records( '_pingback.example.com', 'v=ppf1 ip4:192.0.2.0/24' );

		$resolve_result = null;
		$this->assertFalse( $matcher->evaluate_record_public( 'example.com', '192.0.2.1', 6, $resolve_result ) );
		$this->assertNull( $resolve_result );
	}

	/**
	 * Validate evaluate returns match on OK + true.
	 *
	 * @return void
	 */
	public function test_evaluate_returns_match_on_true() {
		list( $matcher ) = $this->create_matcher();
		$matcher->set_forced_evaluate_record( true, PPF_Record::STATUS_OK );

		$this->assertSame( PPF_Result::MATCH, $matcher->evaluate( 'example.com', '192.0.2.1' ) );
	}

	/**
	 * Validate evaluate returns none on OK + false.
	 *
	 * @return void
	 */
	public function test_evaluate_returns_none_on_false() {
		list( $matcher ) = $this->create_matcher();
		$matcher->set_forced_evaluate_record( false, PPF_Record::STATUS_OK );

		$this->assertSame( PPF_Result::NONE, $matcher->evaluate( 'example.com', '192.0.2.1' ) );
	}

	/**
	 * Validate evaluate returns no_match on OK + null.
	 *
	 * @return void
	 */
	public function test_evaluate_returns_no_match_on_null() {
		list( $matcher ) = $this->create_matcher();
		$matcher->set_forced_evaluate_record( null, PPF_Record::STATUS_OK );

		$this->assertSame( PPF_Result::NO_MATCH, $matcher->evaluate( 'example.com', '192.0.2.1' ) );
	}

	/**
	 * Validate evaluate returns no_record on missing record.
	 *
	 * @return void
	 */
	public function test_evaluate_returns_no_record() {
		list( $matcher ) = $this->create_matcher();
		$matcher->set_forced_evaluate_record( null, PPF_Record::STATUS_NO_RECORD );

		$this->assertSame( PPF_Result::NO_RECORD, $matcher->evaluate( 'example.com', '192.0.2.1' ) );
	}

	/**
	 * Validate evaluate returns invalid on invalid record.
	 *
	 * @return void
	 */
	public function test_evaluate_returns_invalid() {
		list( $matcher ) = $this->create_matcher();
		$matcher->set_forced_evaluate_record( null, PPF_Record::STATUS_INVALID );

		$this->assertSame( PPF_Result::INVALID, $matcher->evaluate( 'example.com', '192.0.2.1' ) );
	}
}
