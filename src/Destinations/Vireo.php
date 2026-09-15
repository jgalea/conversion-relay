<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\NormalizedEvent;

/**
 * Vireo Analytics. Same-site, server-side: events are written straight into
 * Vireo's own buffer via vireo_track_event(), so there is no key to configure
 * and no external request. Available only when the Vireo plugin is active.
 */
final class Vireo extends AbstractDestination {

	public function id(): string {
		return 'vireo';
	}

	public function label(): string {
		return 'Vireo Analytics';
	}

	public function transports(): array {
		return array( self::TRANSPORT_SERVER );
	}

	public function capabilities(): array {
		return array_merge(
			parent::capabilities(),
			array( 'revenue' => true )
		);
	}

	public function settings_fields(): array {
		return array();
	}

	public function is_configured(): bool {
		return function_exists( 'vireo_track_event' );
	}

	public function send_server( NormalizedEvent $event ): DeliveryResult {
		if ( ! function_exists( 'vireo_track_event' ) ) {
			return DeliveryResult::failure( __( 'Vireo Analytics is not active.', 'conversion-relay' ) );
		}

		$value = '';
		if ( isset( $event->meta['search_term'] ) ) {
			$value = (string) $event->meta['search_term'];
		} elseif ( null !== $event->value ) {
			$value = (string) $event->value;
		}

		vireo_track_event( $event->type, $value );

		return DeliveryResult::success( __( 'Recorded to Vireo.', 'conversion-relay' ) );
	}
}
