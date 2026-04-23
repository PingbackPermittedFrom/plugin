<?php
/**
 * Minimal WP_Error stub for isolated tests.
 *
 * @package PPF_Pingback_Direct
 */

/**
 * Minimal test double for WP_Error.
 */
class WP_Error {
	/**
	 * Stored message.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Create the error object.
	 *
	 * @param int    $code Error code.
	 * @param string $message Error message.
	 *
	 * @phpstan-ignore constructor.unusedParameter
	 */
	public function __construct( $code = 0, $message = '' ) {
		$this->message = $message;
	}

	/**
	 * Return the stored error message.
	 *
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}
}
