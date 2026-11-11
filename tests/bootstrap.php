<?php

declare( strict_types=1 );

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( is_readable( $autoload ) ) {
	require_once $autoload;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}

// Minimal i18n pass-throughs so unit tests can exercise plugin code without WordPress.
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { // phpcs:ignore
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore
		return $text;
	}
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) { // phpcs:ignore
		return $text;
	}
}

// Option storage and filters, so unit tests can drive code that reads settings.
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) { // phpcs:ignore
		return $GLOBALS['wpch_test_options'][ $name ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) { // phpcs:ignore
		$GLOBALS['wpch_test_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_json_encode_fallback' ) ) {
	function wp_json_encode_fallback( $data ) { // phpcs:ignore
		return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- wp_json_encode does not exist in the unit suite.
	}
}

// Hook/filter capture, so tests can observe events and drive code that applies filters.
$GLOBALS['wpch_test_filters']    = array();
$GLOBALS['wpch_test_transients'] = array();
$GLOBALS['wpch_test_http']       = array();

function wpch_test_reset(): void { // phpcs:ignore
	$GLOBALS['wpch_test_options']    = array();
	$GLOBALS['wpch_test_filters']    = array();
	$GLOBALS['wpch_test_transients'] = array();
	$GLOBALS['wpch_test_http']       = array();
	$GLOBALS['wpch_test_http_code']  = 200;
	$GLOBALS['wpch_test_scheduled']  = array();
	$GLOBALS['wpch_test_orders']     = array();
	$GLOBALS['wpch_test_search']     = '';
	$GLOBALS['wpch_test_checkout']   = false;
	$GLOBALS['wpch_test_wc']         = null;
	$GLOBALS['wpdb']                 = new \WPConversionHub\Tests\Support\FakeWpdb();
	$_COOKIE                         = array();
	$_SERVER['REMOTE_ADDR']          = '203.0.113.9';
	unset( $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_DNT'] );
	\WPConversionHub\Support\Settings::save( \WPConversionHub\Support\Settings::defaults() );
}

function wpch_test_filter( string $hook, callable $cb ): void { // phpcs:ignore
	$GLOBALS['wpch_test_filters'][ $hook ][] = $cb;
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) { // phpcs:ignore
		foreach ( $GLOBALS['wpch_test_filters'][ $hook ] ?? array() as $cb ) {
			$value = $cb( $value, ...$args );
		}
		return $value;
	}
}

require_once __DIR__ . '/Support/stubs.php';
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {} // phpcs:ignore
}
require_once __DIR__ . '/Support/FakeWpdb.php';
