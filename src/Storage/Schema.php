<?php

declare( strict_types=1 );

namespace WPConversionHub\Storage;

/**
 * Owns the custom table that backs the event log, per-destination dedup markers,
 * and the delivery-attempt record. High-churn data does not belong in options.
 */
final class Schema {

	private const DB_VERSION = '1';
	private const OPTION_KEY = 'wpch_db_version';

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpch_events';
	}

	public static function install(): void {
		global $wpdb;

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id VARCHAR(32) NOT NULL,
			destination VARCHAR(64) NOT NULL,
			type VARCHAR(32) NOT NULL,
			source VARCHAR(64) NOT NULL,
			entity_id VARCHAR(191) NOT NULL DEFAULT '',
			status VARCHAR(16) NOT NULL DEFAULT 'pending',
			attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			payload LONGTEXT NULL,
			response TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_destination (event_id, destination),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::OPTION_KEY, self::DB_VERSION, false );
	}

	public static function maybe_upgrade(): void {
		if ( get_option( self::OPTION_KEY ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function drop(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		delete_option( self::OPTION_KEY );
	}
}
