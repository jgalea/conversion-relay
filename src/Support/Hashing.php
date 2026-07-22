<?php

declare( strict_types=1 );

namespace WPConversionHub\Support;

/**
 * Normalize and SHA-256 hash PII for enhanced conversions, following the
 * conventions Google and Meta expect (lowercase, trim, strip formatting).
 */
final class Hashing {

	public static function email( string $email ): string {
		$email = strtolower( trim( $email ) );
		return '' === $email ? '' : hash( 'sha256', $email );
	}

	public static function phone( string $phone ): string {
		$phone = preg_replace( '/[^0-9]/', '', $phone );
		return ( null === $phone || '' === $phone ) ? '' : hash( 'sha256', $phone );
	}

	public static function name( string $value ): string {
		$value = strtolower( trim( $value ) );
		return '' === $value ? '' : hash( 'sha256', $value );
	}

	/**
	 * Hash a user_data bag. Fields already looking like a 64-char hex hash pass through.
	 *
	 * @param array<string,mixed> $user_data
	 * @return array<string,string>
	 */
	public static function user_data( array $user_data ): array {
		$out = array();
		foreach ( $user_data as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$value = (string) $value;
			if ( self::looks_hashed( $value ) ) {
				$out[ $key ] = strtolower( $value );
				continue;
			}
			if ( 'email' === $key || 'em' === $key ) {
				$out[ $key ] = self::email( $value );
			} elseif ( 'phone' === $key || 'ph' === $key ) {
				$out[ $key ] = self::phone( $value );
			} else {
				$out[ $key ] = self::name( $value );
			}
		}
		return array_filter( $out, static fn( string $v ): bool => '' !== $v );
	}

	private static function looks_hashed( string $value ): bool {
		return 1 === preg_match( '/^[a-f0-9]{64}$/i', $value );
	}
}
