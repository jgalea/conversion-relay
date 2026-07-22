<?php

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wpch_settings' );
delete_option( 'wpch_db_version' );

global $wpdb;
$table = $wpdb->prefix . 'wpch_events';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB
