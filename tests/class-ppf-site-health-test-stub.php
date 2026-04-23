<?php
/**
 * Test stub subclass for PPF Site Health.
 *
 * @package PPF_Pingback_Direct
 */

declare(strict_types=1);

namespace PPF\Pingback\Tests;

use PPF\Pingback\PPF_Site_Health;

/**
 * Exposes protected callback helpers from {@see PPF_Site_Health} for unit tests.
 */
final class PPF_Site_Health_Test_Stub extends PPF_Site_Health {

	/**
	 * Proxy for format_callback_label().
	 *
	 * @param mixed $callback Hook callback.
	 * @return string
	 */
	public static function format_callback_label_exposed( $callback ): string {
		return parent::format_callback_label( $callback );
	}

	/**
	 * Proxy for describe_callback().
	 *
	 * @param mixed $callback Hook callback.
	 * @return array{label:string,file:string|null}
	 */
	public static function describe_callback_exposed( $callback ): array {
		return parent::describe_callback( $callback );
	}
}
