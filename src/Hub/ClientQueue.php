<?php

declare( strict_types=1 );

namespace WPConversionHub\Hub;

/**
 * Redirect-safe buffer for client-side pixel events. Events are persisted per
 * visitor (keyed by a first-party cookie) so a conversion that finishes on a
 * later request — gateway return, order-received, AJAX redirect — still fires its
 * browser pixel exactly once, on the next full page view.
 */
final class ClientQueue {

	private const COOKIE = 'wpch_cq';
	private const TTL    = 900;

	/** @var array<int,array<string,mixed>> */
	private static array $request_buffer = array();

	/**
	 * @param array<string,mixed> $event
	 * @param string[]            $destinations
	 */
	public static function add( array $event, array $destinations ): void {
		if ( empty( $destinations ) ) {
			return;
		}

		$entry = array(
			'event'        => $event,
			'destinations' => array_values( $destinations ),
		);

		self::$request_buffer[] = $entry;

		$key = self::key();
		if ( '' === $key ) {
			return;
		}
		$stored   = get_transient( self::transient( $key ) );
		$stored   = is_array( $stored ) ? $stored : array();
		$stored[] = $entry;
		set_transient( self::transient( $key ), $stored, self::TTL );
	}

	/**
	 * Return everything queued for this visitor (persisted + this request) and clear it.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function flush(): array {
		$events = self::$request_buffer;
		$key    = self::key();
		if ( '' !== $key ) {
			$stored = get_transient( self::transient( $key ) );
			if ( is_array( $stored ) ) {
				$events = array_merge( $stored, $events );
			}
			delete_transient( self::transient( $key ) );
		}

		return self::dedupe( $events );
	}

	public static function ensure_cookie(): void {
		if ( '' !== self::key() || headers_sent() ) {
			return;
		}
		$key  = wp_generate_password( 20, false );
		$path = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		setcookie( self::COOKIE, $key, time() + self::TTL, $path, defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '', is_ssl(), true );
		$_COOKIE[ self::COOKIE ] = $key;
	}

	private static function key(): string {
		return isset( $_COOKIE[ self::COOKIE ] ) ? preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) ) : '';
	}

	private static function transient( string $key ): string {
		return 'wpch_cq_' . $key;
	}

	/**
	 * @param array<int,array<string,mixed>> $events
	 * @return array<int,array<string,mixed>>
	 */
	private static function dedupe( array $events ): array {
		$seen = array();
		$out  = array();
		foreach ( $events as $entry ) {
			$event_id = (string) ( $entry['event']['event_id'] ?? '' );
			foreach ( (array) ( $entry['destinations'] ?? array() ) as $dest ) {
				$k = $event_id . '|' . $dest;
				if ( isset( $seen[ $k ] ) ) {
					continue;
				}
				$seen[ $k ] = true;
			}
			$out[] = $entry;
		}
		return $out;
	}
}
