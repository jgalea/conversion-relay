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
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) { // phpcs:ignore
		return $value;
	}
}

if ( ! function_exists( 'wp_json_encode_fallback' ) ) {
	function wp_json_encode_fallback( $data ) { // phpcs:ignore
		return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- wp_json_encode does not exist in the unit suite.
	}
}
