<?php

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_metadata( 'user', 0, 'wpch_notice_dismissed', '', true );

$wpch_sites = is_multisite() ? get_sites( array( 'fields' => 'ids' ) ) : array( 0 );

foreach ( $wpch_sites as $wpch_site ) {
	if ( is_multisite() ) {
		switch_to_blog( (int) $wpch_site );
	}

	delete_metadata( 'post', 0, '_wpch_visitor', '', true );
	foreach ( array( 'wc_orders_meta', 'edd_ordermeta' ) as $wpch_meta_table ) {
		$wpch_meta_table = $wpdb->prefix . $wpch_meta_table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpch_meta_table ) ) ) === $wpch_meta_table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key = %s', $wpch_meta_table, '_wpch_visitor' ) );
		}
	}

	delete_option( 'wpch_settings' );
	delete_option( 'wpch_db_version' );
	delete_option( 'wpch_ads_events_migrated' );
	delete_option( 'wpch_notice_event_lists' );

	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( '', array(), 'wpch' );
	}
	wp_unschedule_hook( 'wpch_deliver_event' );
	wp_unschedule_hook( 'wpch_prune_log' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
			$wpdb->options,
			$wpdb->esc_like( '_transient_wpch_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_wpch_' ) . '%'
		)
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'wpch_events' ) );

	if ( is_multisite() ) {
		restore_current_blog();
	}
}
