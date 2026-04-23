<?php
/**
 * Test record for PPF checker.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use PPF\Pingback\PPF_Record;

/**
 * Test double returning a fixed record string.
 */
class PPF_Checker_Test_Record extends PPF_Record {
	/**
	 * Record string to return.
	 *
	 * @var string|null
	 */
	private $record;

	/**
	 * Create the test record with a fixed record string.
	 *
	 * @param string|null $record Record string to return.
	 */
	public function __construct( $record ) {
		$this->record = $record;
	}

	/**
	 * Return the configured record.
	 *
	 * @return string|null
	 */
	public function get_record() {
		return $this->record;
	}
}
