<?php
/**
 * Pingback Permitted From (PPF)
 *
 * Constants for PPF authorization check results.
 *
 * @author  Charles Lecklider
 * @license GPL-2.0+
 * @link    https://ppf1.org/
 * @package pingback-permitted-from
 */

namespace PPF\Pingback;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Constants for PPF authorization check results.
 *
 * @phpstan-type CheckResult PPF_Result::*
 */
abstract class PPF_Result {
	public const MATCH     = 'match';
	public const NO_MATCH  = 'no_match';
	public const NONE      = 'none';
	public const UNKNOWN   = 'unknown';
	public const NO_RECORD = 'no_record';
	public const INVALID   = 'invalid_record';
	public const ERROR     = 'error';
}
