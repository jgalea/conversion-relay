<?php

declare( strict_types=1 );

namespace WPConversionHub\Storage;

/**
 * Short-lived home for the visitor's IP, user agent and click ids while an event
 * waits for delivery. Action Scheduler keeps queue args for weeks, so none of it
 * goes there: the queue carries the event id and the worker looks identity up here.
 */
final class IdentityStore {

	private const TTL = DAY_IN_SECONDS;

	/**
	 * @param array<string,string> $identity
	 */
	public static function put( string $event_id, array $identity ): void {
		if ( '' === $event_id || empty( $identity ) ) {
			return;
		}
		set_transient( self::key( $event_id ), $identity, self::TTL );
	}

	/**
	 * @return array<string,string>
	 */
	public static function get( string $event_id ): array {
		$identity = get_transient( self::key( $event_id ) );
		return is_array( $identity ) ? $identity : array();
	}

	public static function forget( string $event_id ): void {
		delete_transient( self::key( $event_id ) );
	}

	private static function key( string $event_id ): string {
		return 'wpch_id_' . substr( $event_id, 0, 32 );
	}
}
