<?php

declare( strict_types=1 );

namespace WPConversionHub\Support;

final class Settings {

	private const OPTION = 'wpch_settings';

	/** @var array<string,mixed>|null */
	private static ?array $cache = null;

	/**
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = is_array( $stored ) ? $stored : array();
		}
		return self::$cache;
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	public static function save( array $settings ): void {
		self::$cache = $settings;
		update_option( self::OPTION, $settings, false );
	}

	public static function defaults(): array {
		return array(
			'sources'              => array(),
			'destinations'         => array(),
			'consent'              => array(
				'require_consent'   => true,
				'respect_dnt'       => true,
				'analytics_default' => 'granted',
				'ads_default'       => 'denied',
			),
			'enhanced_conversions' => false,
		);
	}

	public static function source_enabled( string $id ): bool {
		$all = self::all();
		return ! empty( $all['sources'][ $id ]['enabled'] );
	}

	/**
	 * Delivery channel policy for a source: server_only | client_only | both.
	 */
	public static function source_channel( string $id ): string {
		$all     = self::all();
		$channel = $all['sources'][ $id ]['channel'] ?? 'both';
		return in_array( $channel, array( 'server_only', 'client_only', 'both' ), true ) ? (string) $channel : 'both';
	}

	public static function dest_enabled( string $id ): bool {
		$all = self::all();
		return ! empty( $all['destinations'][ $id ]['enabled'] );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function dest_config( string $id ): array {
		$all = self::all();
		$cfg = $all['destinations'][ $id ] ?? array();
		return is_array( $cfg ) ? $cfg : array();
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function consent(): array {
		$all = self::all();
		$c   = $all['consent'] ?? array();
		return array_merge( self::defaults()['consent'], is_array( $c ) ? $c : array() );
	}

	public static function enhanced_conversions_enabled(): bool {
		return ! empty( self::all()['enhanced_conversions'] );
	}
}
