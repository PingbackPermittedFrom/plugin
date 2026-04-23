<?php
/**
 * Tests for plugin bootstrap functions.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * Coverage for plugin bootstrap behavior.
 *
 * @group ppf-plugin
 */
#[CoversNothing]
class PPF_Plugin_Test extends TestCase {
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
	 * Ensure the plugin file registers its core hooks when loaded.
	 *
	 * @return void
	 */
	public function test_plugin_file_registers_core_hooks_on_load() {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'wp_xmlrpc_server_class', 'PPF\\Pingback\\ppf_xmlrpc_server_class', PHP_INT_MAX );
		Functions\expect( 'add_action' )
			->once()
			->with( 'init', 'PPF\\Pingback\\ppf_init' );
		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_init', 'PPF\\Pingback\\ppf_admin_init' );

		require_once dirname( __DIR__ ) . '/pingback-permitted-from.php';
		$this->addToAssertionCount( 1 );
	}
}
