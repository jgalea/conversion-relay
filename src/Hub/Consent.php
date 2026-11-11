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

	/**
	 * The visitor's choices as seen in their own request, for storing with an
	 * order or submission that is delivered later by a webhook or an admin.
	 *
	 * @return array<string,bool>
	 */
	public static function snapshot(): array {
		$settings   = Settings::consent();
		$marketing  = self::visitor_consent( DestinationInterface::CONSENT_ADS );
		$statistics = self::visitor_consent( DestinationInterface::CONSENT_ANALYTICS );

		return array(
			'marketing'  => $marketing ?? ( 'granted' === ( $settings['ads_default'] ?? 'denied' ) ),
			'statistics' => $statistics ?? ( 'granted' === ( $settings['analytics_default'] ?? 'granted' ) ),
			'dnt'        => self::dnt_enabled(),
		);
	}

	public static function allows( DestinationInterface $destination, NormalizedEvent $event ): bool {
		$settings = Settings::consent();

		// An event with no stored decision that was not raised in the visitor's own
		// request (a gateway webhook, an admin marking an order complete) has no
		// cookie or header worth reading: they would belong to whoever triggered it.
		$deferred = null === $event->consent && $event->identity_resolved;
		$dnt      = null !== $event->consent ? ! empty( $event->consent['dnt'] ) : ( ! $deferred && self::dnt_enabled() );

		if ( ! empty( $settings['respect_dnt'] ) && $dnt ) {
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

		if ( null !== $event->consent ) {
			$granted = ! empty( $event->consent[ DestinationInterface::CONSENT_ADS === $category ? 'marketing' : 'statistics' ] );
			$reason  = 'stored';
		} elseif ( $deferred ) {
			$granted = DestinationInterface::CONSENT_ADS !== $category && $granted;
			$reason  = 'deferred';
		} else {
			$visitor = self::visitor_consent( $category );
			if ( null !== $visitor ) {
				$granted = $visitor;
				$reason  = 'consent_api';
			}
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
