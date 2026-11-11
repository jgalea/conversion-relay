<?php

declare( strict_types=1 );

namespace WPConversionHub\Hub;

/**
 * Per-IP and site-wide budgets for anything an anonymous visitor can trigger
 * that ends up as a log row, a queue row and an outbound request: the public
 * REST route and the front-end hooks (search, checkout) alike.
 *
 * REMOTE_ADDR is the proxy's address on any site behind Cloudflare or a load
 * balancer, which put every visitor in one bucket: one caller could exhaust
 * it and silence everyone else's events. Sites on a trusted proxy supply the
 * real address through wpch_client_ip; the forwarded headers are never read
 * unconditionally, because anyone can set them. The site-wide budget bounds
 * the aggregate regardless of how many addresses a caller can spread across.
 */
final class RateLimit {

	private const KEY_GLOBAL = 'wpch_rl_global';

	public static function ok(): bool {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'anon';
		$ip     = (string) apply_filters( 'wpch_client_ip', $remote );
		$ip     = '' === $ip ? 'anon' : $ip;

		$per_ip = (int) apply_filters( 'wpch_rest_rate_limit', 60 );
		$global = (int) apply_filters( 'wpch_rest_rate_limit_global', 600 );

		$ip_key  = 'wpch_rl_' . md5( $ip );
		$ip_hits = (int) get_transient( $ip_key );
		if ( $ip_hits >= $per_ip ) {
			return false;
		}

		$all_hits = (int) get_transient( self::KEY_GLOBAL );
		if ( $all_hits >= $global ) {
			return false;
		}

		set_transient( $ip_key, $ip_hits + 1, MINUTE_IN_SECONDS );
		set_transient( self::KEY_GLOBAL, $all_hits + 1, MINUTE_IN_SECONDS );
		return true;
	}
}
