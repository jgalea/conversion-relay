<?php

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wpch_settings' );
delete_option( 'wpch_db_version' );

global $wpdb;
$wpch_table = $wpdb->prefix . 'wpch_events';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpch_table ) );
