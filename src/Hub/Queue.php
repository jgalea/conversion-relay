<?php

declare( strict_types=1 );

namespace WPConversionHub\Hub;

use WPConversionHub\Storage\EventLog;

/**
 * Durable async delivery. Uses Action Scheduler when present (WooCommerce bundles
 * it), otherwise falls back to a single wp-cron event. Never blocks the request
 * that produced the conversion.
 */
final class Queue {

	public const HOOK = 'wpch_deliver_event';

	/**
	 * @param array<string,mixed> $event
	 */
	public static function enqueue( array $event, string $destination, int $delay = 0 ): void {
		$args = array(
			'event'       => $event,
			'destination' => $destination,
		);

		if ( function_exists( 'as_enqueue_async_action' ) && 0 === $delay ) {
			as_enqueue_async_action( self::HOOK, array( $args ), 'wpch' );
			return;
		}

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + $delay, self::HOOK, array( $args ), 'wpch' );
			return;
		}

		wp_schedule_single_event( time() + max( 1, $delay ), self::HOOK, array( $args ) );
		spawn_cron();
	}

	/**
	 * Re-queue log rows for another round of attempts. The log keeps no customer
	 * data, so a retry carries the visitor identity only while IdentityStore
	 * still holds it, and no hashed user data.
	 *
	 * @param array<int,array<string,mixed>> $rows
	 */
	public static function retry_rows( array $rows ): int {
		$queued = 0;
		foreach ( $rows as $row ) {
			$payload = json_decode( (string) ( $row['payload'] ?? '' ), true );
			if ( ! is_array( $payload ) ) {
				continue;
			}
			EventLog::reset_for_retry( (string) $row['event_id'], (string) $row['destination'] );
			self::enqueue( $payload, (string) $row['destination'] );
			++$queued;
		}
		return $queued;
	}

	public static function backoff_delay( int $attempts ): int {
		return (int) min( HOUR_IN_SECONDS, ( 2 ** max( 0, $attempts ) ) * MINUTE_IN_SECONDS );
	}
}
