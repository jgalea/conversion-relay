<?php
// WordPress function stubs for the unit suite.

declare( strict_types=1 );

// phpcs:disable

function sanitize_text_field( $v ) {
	return trim( strip_tags( (string) $v ) );
}
function wp_unslash( $v ) {
	return $v;
}
function home_url( $p = '' ) {
	return 'https://example.test' . $p;
}
function add_query_arg( ...$a ) {
	return '/';
}
function wp_hash( $v ) {
	return md5( 'salt' . $v );
}
function is_wp_error( $v ) {
	return false;
}
function sanitize_key( $v ) {
	return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $v ) );
}
function esc_url_raw( $v ) {
	return (string) $v;
}
function map_deep( $v, $cb ) {
	return $v;
}
function current_time( $t, $gmt = 0 ) {
	return gmdate( 'Y-m-d H:i:s' );
}
function set_transient( $k, $v, $ttl = 0 ) {
	$GLOBALS['wpch_test_transients'][ $k ] = $v;
	return true;
}
function get_transient( $k ) {
	return $GLOBALS['wpch_test_transients'][ $k ] ?? false;
}
function delete_transient( $k ) {
	unset( $GLOBALS['wpch_test_transients'][ $k ] );
	return true;
}
function wp_json_encode( $d ) {
	return json_encode( $d );
}
function add_action( ...$a ) {
	return true;
}
function add_filter( ...$a ) {
	return true;
}
function wp_generate_uuid4() {
	return '00000000-0000-4000-8000-000000000000';
}
function wp_safe_remote_post( $url, $args = array() ) {
	$GLOBALS['wpch_test_http'][] = array(
		'url'  => $url,
		'args' => $args,
	);
	return array(
		'response' => array( 'code' => $GLOBALS['wpch_test_http_code'] ?? 200 ),
		'body'     => '{}',
	);
}
function wp_remote_retrieve_response_code( $r ) {
	return $r['response']['code'];
}
function wp_remote_retrieve_body( $r ) {
	return $r['body'];
}
function wp_schedule_single_event( $time, $hook, $args = array() ) {
	$GLOBALS['wpch_test_scheduled'][] = array(
		'hook' => $hook,
		'args' => $args,
	);
	return true;
}
function spawn_cron() {
	return true;
}
function wc_get_order( $id ) {
	return $GLOBALS['wpch_test_orders'][ (int) $id ] ?? false;
}
function is_search() {
	return ! empty( $GLOBALS['wpch_test_search'] );
}
function get_search_query() {
	return (string) ( $GLOBALS['wpch_test_search'] ?? '' );
}
function is_checkout() {
	return ! empty( $GLOBALS['wpch_test_checkout'] );
}
function WC() {
	return $GLOBALS['wpch_test_wc'] ?? null;
}
function delete_option( $name ) {
	unset( $GLOBALS['wpch_test_options'][ $name ] );
	return true;
}
function is_multisite() {
	return false;
}
function as_unschedule_all_actions( $hook = '', $args = array(), $group = '' ) {
	$GLOBALS['wpch_test_unscheduled_group'] = $group;
}
function wp_unschedule_hook( $hook ) {
	$GLOBALS['wpch_test_unscheduled_hooks'][] = $hook;
}
function wp_http_validate_url( $url ) {
	return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : false;
}
