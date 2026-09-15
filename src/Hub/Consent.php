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
 *
 * When the WP Consent API is installed, the visitor's actual choice is the
 * signal. Without it there is nothing to read, so the configured per-category
 * default stands in, which is why "Gate destinations on consent" on its own
 * cannot do more than apply a policy.
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
		$reason  = 'default';

		$visitor = self::visitor_consent( $category );
		if ( null !== $visitor ) {
			$granted = $visitor;
			$reason  = 'consent_api';
		}

		return (bool) apply_filters( 'wpch_consent', $granted, $category, $event, $reason );
	}

	/**
	 * The visitor's own choice, via the WP Consent API, or null when that plugin
	 * is not installed and there is no choice to read.
	 */
	private static function visitor_consent( string $category ): ?bool {
		if ( ! function_exists( 'wp_has_consent' ) ) {
			return null;
		}

		$mapped = DestinationInterface::CONSENT_ADS === $category ? 'marketing' : 'statistics';

		return (bool) wp_has_consent( $mapped );
	}

	private static function dnt_enabled(): bool {
		return isset( $_SERVER['HTTP_DNT'] ) && '1' === sanitize_text_field( wp_unslash( $_SERVER['HTTP_DNT'] ) );
	}
}
