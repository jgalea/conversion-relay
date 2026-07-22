<?php

declare( strict_types=1 );

namespace WPConversionHub\Cli;

use WPConversionHub\Event\EventType;
use WPConversionHub\Event\NormalizedEvent;
use WPConversionHub\Plugin;
use WPConversionHub\Storage\EventLog;
use WPConversionHub\Support\Settings;

/**
 * WP-CLI: wp conversion-hub <status|destinations|test-event|retry>
 */
final class Commands {

	/**
	 * Show delivery counts by status.
	 */
	public function status(): void {
		$counts = array();
		foreach ( EventLog::recent( 500 ) as $row ) {
			$status            = (string) ( $row['status'] ?? 'unknown' );
			$counts[ $status ] = ( $counts[ $status ] ?? 0 ) + 1;
		}
		if ( empty( $counts ) ) {
			\WP_CLI::log( __( 'No events recorded yet.', 'wp-conversion-hub' ) );
			return;
		}
		foreach ( $counts as $status => $n ) {
			\WP_CLI::log( sprintf( '%-10s %d', $status, $n ) );
		}
	}

	/**
	 * List registered destinations and whether they are enabled and configured.
	 */
	public function destinations(): void {
		$rows = array();
		foreach ( Plugin::instance()->registry()->destinations() as $dest ) {
			$rows[] = array(
				'id'         => $dest->id(),
				'label'      => $dest->label(),
				'transports' => implode( '+', $dest->transports() ),
				'enabled'    => Settings::dest_enabled( $dest->id() ) ? 'yes' : 'no',
				'configured' => $dest->is_configured() ? 'yes' : 'no',
			);
		}
		if ( empty( $rows ) ) {
			\WP_CLI::log( __( 'No destinations registered.', 'wp-conversion-hub' ) );
			return;
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'label', 'transports', 'enabled', 'configured' ) );
	}

	/**
	 * Dispatch a synthetic event through the hub.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Event type (default: purchase).
	 *
	 * [--value=<value>]
	 * : Monetary value (default: 9.99).
	 *
	 * @param array<int,string>    $args
	 * @param array<string,string> $assoc
	 */
	public function test_event( array $args, array $assoc ): void {
		$type  = $assoc['type'] ?? EventType::PURCHASE;
		$value = isset( $assoc['value'] ) ? (float) $assoc['value'] : 9.99;

		if ( ! EventType::is_valid( $type ) ) {
			\WP_CLI::error(
				sprintf(
					/* translators: %s: the invalid event type the user passed via --type. */
					__( 'Invalid event type: %s', 'wp-conversion-hub' ),
					$type
				)
			);
		}

		Plugin::instance()->dispatcher()->dispatch(
			NormalizedEvent::create(
				array(
					'type'      => $type,
					'source'    => 'cli',
					'entity_id' => 'cli-' . time(),
					'value'     => $value,
					'currency'  => 'USD',
				)
			)
		);

		\WP_CLI::success(
			sprintf(
				/* translators: %s: the dispatched event type, e.g. "purchase". */
				__( 'Dispatched a %s event. Check `wp conversion-hub status`.', 'wp-conversion-hub' ),
				$type
			)
		);
	}

	/**
	 * Re-queue failed / dead-lettered deliveries.
	 */
	public function retry(): void {
		$n = 0;
		foreach ( EventLog::recent( 200, EventLog::STATUS_FAILED ) as $row ) {
			$payload = json_decode( (string) ( $row['payload'] ?? '' ), true );
			if ( ! is_array( $payload ) ) {
				continue;
			}
			\WPConversionHub\Hub\Queue::enqueue( $payload, (string) $row['destination'] );
			++$n;
		}
		\WP_CLI::success(
			sprintf(
				/* translators: %d: number of deliveries re-queued. */
				__( 'Re-queued %d failed deliveries.', 'wp-conversion-hub' ),
				$n
			)
		);
	}
}
