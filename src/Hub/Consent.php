<?php

declare( strict_types=1 );

namespace WPConversionHub\Hub;

use WPConversionHub\Destinations\DestinationInterface;
use WPConversionHub\Event\NormalizedEvent;
use WPConversionHub\Support\Settings;

/**
 * Server-shaped consent gate. Advertising destinations default-deny until consent
 * is granted. Integrators refine the decision through the `wpch_consent` filter,
 * which receives full request context so it works where no browser cookie is
 * readable (checkout, REST, cron, webhooks).
 */
final class Consent {

	public static function allows( DestinationInterface $destination, NormalizedEvent $event ): bool {
		$settings = Settings::consent();

		if ( ! empty( $settings['respect_dnt'] ) && self::dnt_enabled() ) {
			return (bool) apply_filters( 'wpch_consent', false, $destination->consent_category(), $event, 'dnt' );
		}

		if ( empty( $settings['require_consent'] ) ) {
			return (bool) apply_filters( 'wpch_consent', true, $destination->consent_category(), $event, 'disabled' );
		}

		$category = $destination->consent_category();
		$default  = DestinationInterface::CONSENT_ADS === $category
			? ( $settings['ads_default'] ?? 'denied' )
			: ( $settings['analytics_default'] ?? 'granted' );

		$granted = 'granted' === $default;

		return (bool) apply_filters( 'wpch_consent', $granted, $category, $event, 'default' );
	}

	private static function dnt_enabled(): bool {
		return isset( $_SERVER['HTTP_DNT'] ) && '1' === (string) $_SERVER['HTTP_DNT'];
	}
}
