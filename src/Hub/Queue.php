<?php

declare( strict_types=1 );

namespace WPConversionHub\Hub;

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

	public static function backoff_delay( int $attempts ): int {
		return (int) min( HOUR_IN_SECONDS, ( 2 ** max( 0, $attempts ) ) * MINUTE_IN_SECONDS );
	}
}
