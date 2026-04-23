<?php
/**
 * Test callback used for XML-RPC Site Health conflict detection.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

/**
 * Dummy callback used to exercise XML-RPC conflict source reporting.
 *
 * @param string $class_name XML-RPC server class.
 * @return string
 */
function ppf_site_health_test_conflict_callback( $class_name ) {
	return $class_name;
}
