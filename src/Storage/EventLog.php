<?php

declare( strict_types=1 );

namespace WPConversionHub\Storage;

/** Per-destination delivery ledger backing the custom table: dedup, status, retry count. */
final class EventLog {

	public const STATUS_PENDING = 'pending';
	public const STATUS_SENT    = 'sent';
	public const STATUS_FAILED  = 'failed';
	public const STATUS_DEAD    = 'dead';
	public const STATUS_SKIPPED = 'skipped';

	public const MAX_ATTEMPTS = 5;

	/**
	 * Record an intent to deliver an event to a destination. Returns false when a
	 * successful (or in-flight) row already exists — the dedup guard.
	 *
	 * @param array<string,mixed> $payload
	 */
	public static function record_pending( string $event_id, string $destination, string $type, string $source, string $entity_id, array $payload ): bool {
		global $wpdb;
		$table = Schema::table();
		$now   = current_time( 'mysql', true );

		if ( self::already_sent( $event_id, $destination ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (event_id, destination, type, source, entity_id, status, attempts, payload, created_at, updated_at)
				 VALUES (%s, %s, %s, %s, %s, %s, 0, %s, %s, %s)
				 ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)',
				$table,
				$event_id,
				$destination,
				$type,
				$source,
				$entity_id,
				self::STATUS_PENDING,
				(string) wp_json_encode( $payload ),
				$now,
				$now
			)
		);

		return (bool) $inserted;
	}

	public static function already_sent( string $event_id, string $destination ): bool {
		global $wpdb;
		$table = Schema::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$status = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT status FROM %i WHERE event_id = %s AND destination = %s',
				$table,
				$event_id,
				$destination
			)
		);
		return self::STATUS_SENT === $status;
	}

	public static function mark_sent( string $event_id, string $destination, string $response = '' ): void {
		self::update_status( $event_id, $destination, self::STATUS_SENT, $response );
	}

	public static function mark_failed( string $event_id, string $destination, string $response, int $attempts ): void {
		$status = $attempts >= self::MAX_ATTEMPTS ? self::STATUS_DEAD : self::STATUS_FAILED;
		self::update_status( $event_id, $destination, $status, $response );
	}

	private static function update_status( string $event_id, string $destination, string $status, string $response ): void {
		global $wpdb;
		$table = Schema::table();
		$now   = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET status = %s, response = %s, updated_at = %s, attempts = attempts + 1 WHERE event_id = %s AND destination = %s',
				$table,
				$status,
				mb_substr( $response, 0, 500 ),
				$now,
				$event_id,
				$destination
			)
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 50, ?string $status = null ): array {
		global $wpdb;
		$table = Schema::table();
		$limit = max( 1, min( 500, $limit ) );

		if ( null !== $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s ORDER BY id DESC LIMIT %d',
					$table,
					$status,
					$limit
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', $table, $limit ),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	public static function prune( int $keep_days = 30 ): void {
		global $wpdb;
		$table  = Schema::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $keep_days * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE status IN ('sent','skipped') AND created_at < %s",
				$table,
				$cutoff
			)
		);
	}
}
