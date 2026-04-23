<?php
/**
 * Pingback Permitted From (PPF)
 *
 * Advisory helpers for PPF authorization.
 *
 * @author  Charles Lecklider
 * @license GPL-2.0+
 * @link    https://ppf1.org/
 * @package pingback-permitted-from
 */

namespace PPF\Pingback;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check X-Pingback-Advisory header for advisory tokens.
 *
 * @see https://invis.net/specs/pingback-advisory-extension/
 */
class PPF_Advisory {
	/**
	 * Comment meta data.
	 *
	 * @var array<string,mixed>
	 */
	protected $comment_meta = array();

	/**
	 * Create a new PPF_Advisory instance.
	 */
	public function __construct() {
		add_filter( 'pingback_pre_remote_get', array( $this, 'pre_remote_get' ), 10 );
		add_filter( 'pingback_pre_comment_meta', array( $this, 'pre_comment_meta' ), 10 );

        // phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
		add_filter( 'ppf_advisory_token-auth:basic', array( $this, 'token_auth_basic' ), 10, 2 );
		add_filter( 'ppf_advisory_token-auth:bearer', array( $this, 'token_auth_bearer' ), 10, 2 );
		add_filter( 'ppf_advisory_token-automated', array( $this, 'token_automated' ), 10 );
		add_filter( 'ppf_advisory_token-private', array( $this, 'token_private' ), 10 );
        // phpcs:enable WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Check the advisory tokens.
	 *
	 * Hooked after a successful PPF authorization.
	 *
	 * @param array<string,array<string,mixed>> $http_api_args HTTP API arguments.
	 * @return array<string,array<string,mixed>> Filtered HTTP API arguments.
	 */
	public function pre_remote_get( array $http_api_args ): array {
		if ( isset( $_SERVER['HTTP_X_PINGBACK_ADVISORY'] ) && is_string( $_SERVER['HTTP_X_PINGBACK_ADVISORY'] ) ) {
            // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- This is the validation path.
			// Keep the raw header in case anything else needs it.
			$this->comment_meta['advisory_raw'] = wp_unslash( $_SERVER['HTTP_X_PINGBACK_ADVISORY'] );

			// Advisory is a comma-separated list of tokens, some of which may be key-value pairs.
			// We're not sanitizing as we're checking against a list of known tokens.
			$tokens = explode( ',', $this->comment_meta['advisory_raw'] );
			foreach ( $tokens as $advisory_token ) {
				$token = trim( $advisory_token );
				if ( false !== strpos( $token, '=' ) ) {
					list( $token, $value ) = explode( '=', $token, 2 );
					$value                 = trim( $value );
				} else {
					$value = null;
				}
				$token = strtolower( $token );

				/**
				 * Filters the PPF advisory token.
				 *
				 * @param array<string,array<string,mixed>> $http_api_args HTTP API arguments.
				 * @param string|null                       $value         PPF advisory value.
				 */
				$http_api_args = apply_filters( "ppf_advisory_token-{$token}", $http_api_args, $value ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

				/**
				 * Filters the PPF comment meta for an advisory token.
				 *
				 * @param array<string,array<string,mixed>> $http_api_args HTTP API arguments.
				 * @param string                            $token         PPF advisory token.
				 * @param string|null                       $value         PPF advisory value.
				 */
				$http_api_args = apply_filters( 'ppf_advisory_token', $http_api_args, $token, $value );
			}
		}

		return $http_api_args;
	}

	/**
	 * Filter the pingback comment meta for advisory tokens.
	 *
	 * @param array<string,mixed> $comment_meta Pingback comment meta data.
	 * @return array<string,mixed> Filtered comment meta.
	 */
	public function pre_comment_meta( array $comment_meta ): array {
		$comment_meta = array_merge( $comment_meta, $this->comment_meta );

		return $comment_meta;
	}

	/**
	 * Apply auth:basic advisory token.
	 *
	 * @param array<string,array<string,mixed>> $http_api_args HTTP API arguments.
	 * @param string|null                       $value         Token value (base64-encoded credentials) or null.
	 * @return array<string,array<string,mixed>> Filtered HTTP API arguments.
	 */
	public function token_auth_basic( array $http_api_args, ?string $value ): array {
		if ( $value > '' ) {
			$this->comment_meta['advisory_auth_basic'] = $value;
			$http_api_args['headers']['Authorization'] = 'Basic ' . $value; // Already base64 encoded.
		} else {
			$this->comment_meta['advisory_auth_basic'] = true;
		}

		return $http_api_args;
	}

	/**
	 * Apply auth:bearer advisory token.
	 *
	 * @param array<string,array<string,mixed>> $http_api_args HTTP API arguments.
	 * @param string|null                       $value         Token value or null.
	 * @return array<string,array<string,mixed>> Filtered HTTP API arguments.
	 */
	public function token_auth_bearer( array $http_api_args, ?string $value ): array {
		if ( $value > '' ) {
			$this->comment_meta['advisory_auth_bearer'] = $value;
			$http_api_args['headers']['Authorization']  = 'Bearer ' . $value;
		} else {
			$this->comment_meta['advisory_auth_bearer'] = true;
		}

		return $http_api_args;
	}

	/**
	 * Apply automated advisory token.
	 *
	 * @param array<string,array<string,mixed>> $http_api_args HTTP API arguments.
	 * @return array<string,array<string,mixed>> Filtered HTTP API arguments.
	 */
	public function token_automated( array $http_api_args ): array {
		$this->comment_meta['advisory_automated'] = true;
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validation path.
			$this->comment_meta['advisory_user_agent'] = wp_unslash( $_SERVER['HTTP_USER_AGENT'] );
            // phpcs:enable
		}

		return $http_api_args;
	}

	/**
	 * Apply private advisory token.
	 *
	 * @param array<string,array<string,mixed>> $http_api_args HTTP API arguments.
	 * @return array<string,array<string,mixed>> Filtered HTTP API arguments.
	 */
	public function token_private( array $http_api_args ): array {
		$this->comment_meta['advisory_private'] = true;
		return $http_api_args;
	}
}
