<?php

declare( strict_types=1 );

namespace WPConversionHub\Support;

final class Settings {

	private const OPTION = 'wpch_settings';

	public const NOTICE_OPTION = 'wpch_notice_event_lists';

	private const MIGRATED_OPTION = 'wpch_ads_events_migrated';

	private const DEFAULT_EVENTS = array(
		'google_ads' => array( 'purchase' ),
		'meta'       => array( 'purchase', 'add_to_cart', 'begin_checkout', 'view_item', 'search', 'register', 'subscribe', 'form_submission', 'donation' ),
	);

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

	/**
	 * Whether a destination takes this event type. An unset list means the
	 * destination's default: every event, except where a type would be miscounted
	 * as a conversion or has no standard name on the platform.
	 */
	public static function dest_accepts( string $id, string $type ): bool {
		$events = self::dest_events( $id );
		return null === $events || in_array( $type, $events, true );
	}

	/**
	 * The event types a destination takes, or null when it takes everything.
	 *
	 * @return string[]|null
	 */
	public static function dest_events( string $id ): ?array {
		$events = self::dest_config( $id )['events'] ?? null;
		if ( ! is_array( $events ) ) {
			$events = self::DEFAULT_EVENTS[ $id ] ?? null;
		}
		return $events;
	}

	/**
	 * Google Ads used to send every event to one label. On an upgrade, a site with
	 * that label keeps its lead events going to it, in the new per-event slots, and
	 * gets a one-time notice about the lists.
	 */
	public static function migrate_google_ads_events(): void {
		if ( false !== get_option( self::MIGRATED_OPTION ) ) {
			return;
		}
		update_option( self::MIGRATED_OPTION, 1, false );

		$all   = self::all();
		$cfg   = self::dest_config( 'google_ads' );
		$label = isset( $cfg['conversion_label'] ) && is_scalar( $cfg['conversion_label'] ) ? (string) $cfg['conversion_label'] : '';
		if ( '' === $label || is_array( $cfg['events'] ?? null ) ) {
			return;
		}

		$leads = array( 'form_submission', 'subscribe', 'register', 'donation' );
		foreach ( $leads as $type ) {
			if ( empty( $cfg[ 'label_' . $type ] ) ) {
				$cfg[ 'label_' . $type ] = $label;
			}
		}
		$cfg['events'] = array_merge( self::DEFAULT_EVENTS['google_ads'], $leads );

		$all['destinations']['google_ads'] = $cfg;
		self::save( $all );
		update_option( self::NOTICE_OPTION, 1, false );
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
