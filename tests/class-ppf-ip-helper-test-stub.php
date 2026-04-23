<?php
/**
 * Test stub for exposing protected IP helper behavior.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use PPF\Pingback\PPF_IP_Helper;

/**
 * Exposes protected IP helper behavior for tests.
 */
class PPF_IP_Helper_Test_Stub extends PPF_IP_Helper {
	/**
	 * Expose the protected trusted-proxy check for unit tests.
	 *
	 * @param string $remote_addr Direct peer IP.
	 * @return bool
	 */
	public function call_is_trusted_proxy( $remote_addr ) {
		return $this->is_trusted_proxy( $remote_addr );
	}
}
