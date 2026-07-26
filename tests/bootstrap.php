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
