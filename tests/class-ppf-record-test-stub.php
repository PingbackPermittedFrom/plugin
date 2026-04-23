<?php
/**
 * Test stub exposing record internals.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use PPF\Pingback\PPF_Record;

/**
 * Test wrapper to expose protected record methods.
 */
class PPF_Record_Test_Stub extends PPF_Record {
	/**
	 * Expose normalize_host for testing.
	 *
	 * @param string $host Hostname to normalize.
	 * @return string
	 */
	public function normalize_host_public( string $host ): string {
		return $this->normalize_host( $host );
	}
}
