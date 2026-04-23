<?php
/**
 * PHPUnit bootstrap for PPF Pingback Direct tests.
 *
 * @package PPF_Pingback_Direct
 */

/**
 * Get the value of a constant.
 *
 * @param  string $name Constant name.
 * @return mixed|null Constant value.
 */
function _constant( string $name ): mixed {
	try {
		return constant( $name );
	} catch ( \Error $e ) {
		return null;
	}
}

/**
 * Check if a constant is defined.
 *
 * @param  string $name Constant name.
 * @return bool Constant is defined.
 */
function _defined( string $name ): bool {
	return defined( $name );
}

require_once dirname( __DIR__ ) . '/vendor/antecedent/patchwork/Patchwork.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require_once dirname( __DIR__ ) . '/tests/stubs/class-wp-error.php';
require_once dirname( __DIR__ ) . '/wp-includes/ppf/class-ppf-result.php';
