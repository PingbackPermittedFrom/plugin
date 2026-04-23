<?php
/**
 * Tests for PPF_Site_Health callback labeling and reflection helpers.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PPF\Pingback\PPF_Site_Health;

require_once dirname( __DIR__ ) . '/wp-includes/ppf/class-ppf-site-health.php';
require_once __DIR__ . '/class-ppf-site-health-test-stub.php';
require_once __DIR__ . '/class-ppf-site-health-test-conflict-callback.php';

/**
 * Covers format_callback_label and describe_callback via {@see PPF_Site_Health_Test_Stub}.
 *
 * @group ppf-site-health
 */
#[CoversClass( PPF_Site_Health::class )]
final class PPF_Site_Health_Callback_Description_Test extends TestCase {

	/**
	 * String callbacks are returned unchanged from format_callback_label.
	 *
	 * @return void
	 */
	public function test_format_callback_label_returns_string_callback_unchanged(): void {
		$label = PPF_Site_Health_Test_Stub::format_callback_label_exposed( 'my_plugin_swap_xmlrpc' );
		$this->assertSame( 'my_plugin_swap_xmlrpc', $label );
	}

	/**
	 * Array callable with class string and method uses Class::method label.
	 *
	 * @return void
	 */
	public function test_format_callback_label_static_class_method_array_uses_two_strings(): void {
		$label = PPF_Site_Health_Test_Stub::format_callback_label_exposed(
			array( self::class, 'fixture_static_method' )
		);
		$this->assertSame( self::class . '::fixture_static_method', $label );
	}

	/**
	 * Array callable with object uses get_class() for the left-hand side.
	 *
	 * @return void
	 */
	public function test_format_callback_label_object_and_method_uses_get_class(): void {
		$obj   = new class() {
			/**
			 * Instance method for label test.
			 *
			 * @return void
			 */
			public function instance_method(): void {
			}
		};
		$class = get_class( $obj );
		$label = PPF_Site_Health_Test_Stub::format_callback_label_exposed( array( $obj, 'instance_method' ) );
		$this->assertSame( $class . '::instance_method', $label );
	}

	/**
	 * Null first element means isset() fails; label falls through to unknown callback.
	 *
	 * @return void
	 */
	public function test_format_callback_label_array_with_null_element_is_unknown_callback(): void {
		$label = PPF_Site_Health_Test_Stub::format_callback_label_exposed( array( null, 'ghost' ) );
		$this->assertSame( 'unknown callback', $label );
	}

	/**
	 * False first element still isset; uses unknown target branch.
	 *
	 * @return void
	 */
	public function test_format_callback_label_array_false_target_uses_unknown_target_branch(): void {
		$label = PPF_Site_Health_Test_Stub::format_callback_label_exposed( array( false, 'ghost' ) );
		$this->assertSame( 'unknown target::ghost', $label );
	}

	/**
	 * Non-object non-string target uses unknown target wording.
	 *
	 * @return void
	 */
	public function test_format_callback_label_array_with_non_object_non_string_target_is_unknown_target(): void {
		$label = PPF_Site_Health_Test_Stub::format_callback_label_exposed( array( true, 'proc' ) );
		$this->assertSame( 'unknown target::proc', $label );
	}

	/**
	 * Non-string method name is coerced when building the label.
	 *
	 * @return void
	 */
	public function test_format_callback_label_array_proc_coerces_to_string(): void {
		$label = PPF_Site_Health_Test_Stub::format_callback_label_exposed( array( 'NS\\Type', 99 ) );
		$this->assertSame( 'NS\\Type::99', $label );
	}

	/**
	 * Closures are labeled as Closure.
	 *
	 * @return void
	 */
	public function test_format_callback_label_closure(): void {
		$closure = static function (): void {
		};
		$label   = PPF_Site_Health_Test_Stub::format_callback_label_exposed( $closure );
		$this->assertSame( 'Closure', $label );
	}

	/**
	 * Scalars and generic objects yield unknown callback.
	 *
	 * @return void
	 */
	public function test_format_callback_label_unknown_mixed_input(): void {
		$this->assertSame( 'unknown callback', PPF_Site_Health_Test_Stub::format_callback_label_exposed( 42 ) );
		$this->assertSame( 'unknown callback', PPF_Site_Health_Test_Stub::format_callback_label_exposed( null ) );
		$this->assertSame( 'unknown callback', PPF_Site_Health_Test_Stub::format_callback_label_exposed( new \stdClass() ) );
	}

	/**
	 * Single-element array is not a [0],[1] callable shape.
	 *
	 * @return void
	 */
	public function test_format_callback_label_array_missing_second_index_not_array_shape(): void {
		$label = PPF_Site_Health_Test_Stub::format_callback_label_exposed( array( 'OnlyOne' ) );
		$this->assertSame( 'unknown callback', $label );
	}

	/**
	 * Userland function string resolves file via ReflectionFunction.
	 *
	 * @return void
	 */
	public function test_describe_callback_string_user_function_has_file_path(): void {
		$out = PPF_Site_Health_Test_Stub::describe_callback_exposed(
			__NAMESPACE__ . '\\ppf_site_health_test_conflict_callback'
		);
		$this->assertSame( __NAMESPACE__ . '\\ppf_site_health_test_conflict_callback', $out['label'] );
		$this->assertIsString( $out['file'] );
		$this->assertStringEndsWith( 'class-ppf-site-health-test-conflict-callback.php', $out['file'] );
	}

	/**
	 * Missing function name yields null file.
	 *
	 * @return void
	 */
	public function test_describe_callback_string_missing_function_has_null_file(): void {
		$out = PPF_Site_Health_Test_Stub::describe_callback_exposed(
			'ppf_definitely_missing_function_name_xyz_9f3a'
		);
		$this->assertSame( 'ppf_definitely_missing_function_name_xyz_9f3a', $out['label'] );
		$this->assertNull( $out['file'] );
	}

	/**
	 * Internal PHP functions have no user file path.
	 *
	 * @return void
	 */
	public function test_describe_callback_internal_string_function_yields_null_file(): void {
		$out = PPF_Site_Health_Test_Stub::describe_callback_exposed( 'strlen' );
		$this->assertSame( 'strlen', $out['label'] );
		$this->assertNull( $out['file'] );
	}

	/**
	 * Static method array resolves declaring file.
	 *
	 * @return void
	 */
	public function test_describe_callback_static_method_array_resolves_file(): void {
		$out            = PPF_Site_Health_Test_Stub::describe_callback_exposed(
			array( PPF_Site_Health_Test_Stub::class, 'format_callback_label_exposed' )
		);
		$expected_label = PPF_Site_Health_Test_Stub::class . '::format_callback_label_exposed';
		$this->assertSame( $expected_label, $out['label'] );
		$this->assertIsString( $out['file'] );
		$this->assertStringEndsWith( 'class-ppf-site-health-test-stub.php', $out['file'] );
	}

	/**
	 * Instance method array resolves declaring file.
	 *
	 * @return void
	 */
	public function test_describe_callback_instance_method_array_resolves_file(): void {
		$obj = new class() {
			/**
			 * Method for reflection file path test.
			 *
			 * @return void
			 */
			public function foo(): void {
			}
		};
		$out = PPF_Site_Health_Test_Stub::describe_callback_exposed( array( $obj, 'foo' ) );
		$this->assertStringContainsString( '::foo', $out['label'] );
		$this->assertIsString( $out['file'] );
		$this->assertStringEndsWith( 'PPF_Site_Health_Callback_Description_Test.php', $out['file'] );
	}

	/**
	 * Invalid method triggers ReflectionException; file is null.
	 *
	 * @return void
	 */
	public function test_describe_callback_array_invalid_method_sets_null_file_after_reflection_exception(): void {
		$out            = PPF_Site_Health_Test_Stub::describe_callback_exposed(
			array( PPF_Site_Health_Test_Stub::class, 'method_that_does_not_exist___' )
		);
		$expected_label = PPF_Site_Health_Test_Stub::class . '::method_that_does_not_exist___';
		$this->assertSame( $expected_label, $out['label'] );
		$this->assertNull( $out['file'] );
	}

	/**
	 * Closure callback yields Closure label and defining file.
	 *
	 * @return void
	 */
	public function test_describe_callback_closure_has_label_and_optional_file(): void {
		$closure = static function (): void {
		};
		$out     = PPF_Site_Health_Test_Stub::describe_callback_exposed( $closure );
		$this->assertSame( 'Closure', $out['label'] );
		$this->assertIsString( $out['file'] );
		$this->assertStringEndsWith( 'PPF_Site_Health_Callback_Description_Test.php', $out['file'] );
	}

	/**
	 * Unsupported shapes only populate label.
	 *
	 * @return void
	 */
	public function test_describe_callback_unknown_shape_only_label_branch(): void {
		$out = PPF_Site_Health_Test_Stub::describe_callback_exposed( new \stdClass() );
		$this->assertSame( 'unknown callback', $out['label'] );
		$this->assertNull( $out['file'] );
	}

	/**
	 * Non-string method index skips ReflectionMethod branch.
	 *
	 * @return void
	 */
	public function test_describe_callback_array_non_string_method_skips_reflection(): void {
		$obj   = new \stdClass();
		$out   = PPF_Site_Health_Test_Stub::describe_callback_exposed( array( $obj, 404 ) );
		$class = get_class( $obj );
		$this->assertSame( $class . '::404', $out['label'] );
		$this->assertNull( $out['file'] );
	}

	/**
	 * Static fixture used as array callable target.
	 *
	 * @return void
	 */
	public static function fixture_static_method(): void {
	}
}
