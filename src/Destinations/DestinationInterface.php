<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\NormalizedEvent;

interface DestinationInterface {

	public const TRANSPORT_SERVER = 'server';
	public const TRANSPORT_CLIENT = 'client';

	public const CONSENT_ANALYTICS = 'analytics';
	public const CONSENT_ADS       = 'advertising';

	public const TIER_FREE = 'free';
	public const TIER_PRO  = 'pro';

	public function id(): string;

	public function label(): string;

	/**
	 * TIER_FREE or TIER_PRO. Pro destinations only deliver with a valid license.
	 */
	public function tier(): string;

	/**
	 * Short line shown in the admin when a Pro destination is locked.
	 */
	public function pro_note(): string;

	/**
	 * @return string[] One or both of TRANSPORT_SERVER / TRANSPORT_CLIENT.
	 */
	public function transports(): array;

	/**
	 * Consent category this destination belongs to (analytics or advertising).
	 */
	public function consent_category(): string;

	/**
	 * Which normalized fields this destination can actually use.
	 *
	 * @return array<string,bool> keys: items, user_data, revenue, dedup, server, client
	 */
	public function capabilities(): array;

	/**
	 * Admin settings schema: [ key => [ 'label' =>, 'type' =>, 'secret' => bool ] ].
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function settings_fields(): array;

	public function is_configured(): bool;

	/**
	 * Server transport: deliver the event now (called from the async queue worker).
	 */
	public function send_server( NormalizedEvent $event ): DeliveryResult;

	/**
	 * Client transport: config handed to bridge.js so it can fire the pixel.
	 *
	 * @return array<string,mixed>
	 */
	public function client_config(): array;
}
