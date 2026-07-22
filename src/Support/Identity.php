<?php

declare( strict_types=1 );

namespace WPConversionHub\Support;

/**
 * Captures first-party click and analytics identifiers so server-side sends can
 * be deduplicated and attributed against their client-side counterparts.
 */
final class Identity {

	/**
	 * @return array<string,string>
	 */
	public static function capture(): array {
		$identity = array();

		$ga_client_id = self::ga_client_id();
		if ( '' !== $ga_client_id ) {
			$identity['client_id'] = $ga_client_id;
		}

		foreach ( array(
			'_fbp' => 'fbp',
			'_fbc' => 'fbc',
		) as $cookie => $key ) {
			$value = self::cookie( $cookie );
			if ( '' !== $value ) {
				$identity[ $key ] = $value;
			}
		}

		foreach ( array( 'gclid', 'wbraid', 'gbraid' ) as $param ) {
			$value = self::click_id( $param );
			if ( '' !== $value ) {
				$identity[ $param ] = $value;
			}
		}

		$identity['ip']         = self::ip();
		$identity['user_agent'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return array_filter( $identity, static fn( string $v ): bool => '' !== $v );
	}

	private static function ga_client_id(): string {
		$ga = self::cookie( '_ga' );
		if ( '' === $ga ) {
			return '';
		}
		// _ga cookie: GA1.1.XXXXXXXXX.XXXXXXXXX -> client id is the last two segments.
		$parts = explode( '.', $ga );
		if ( count( $parts ) >= 4 ) {
			return $parts[2] . '.' . $parts[3];
		}
		return '';
	}

	private static function click_id( string $param ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- inbound ad click id on a plain landing URL, no nonce is possible.
		if ( isset( $_GET[ $param ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tracking parameter, sanitized below.
			return sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
		}
		return self::cookie( 'wpch_' . $param );
	}

	private static function cookie( string $name ): string {
		return isset( $_COOKIE[ $name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ) : '';
	}

	private static function ip(): string {
		if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
