<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\EventType;
use WPConversionHub\Event\NormalizedEvent;

/**
 * Google Analytics 4. Client transport via gtag, server transport via the
 * Measurement Protocol. Shares event_id / transaction_id across both channels so
 * GA4 deduplicates the pair.
 */
final class Ga4 extends AbstractDestination {

	private const MP_ENDPOINT = 'https://www.google-analytics.com/mp/collect';

	public function id(): string {
		return 'ga4';
	}

	public function label(): string {
		return 'Google Analytics 4';
	}

	public function transports(): array {
		$transports = array( self::TRANSPORT_CLIENT );
		if ( '' !== $this->get( 'api_secret' ) ) {
			$transports[] = self::TRANSPORT_SERVER;
		}
		return $transports;
	}

	public function capabilities(): array {
		return array_merge(
			parent::capabilities(),
			array(
				'items'   => true,
				'revenue' => true,
				'dedup'   => true,
			)
		);
	}

	public function settings_fields(): array {
		return array(
			'measurement_id' => array(
				'label'  => __( 'Measurement ID (G-XXXXXXX)', 'wp-conversion-hub' ),
				'type'   => 'text',
				'secret' => false,
			),
			'api_secret'     => array(
				'label'  => __( 'Measurement Protocol API secret', 'wp-conversion-hub' ),
				'type'   => 'text',
				'secret' => true,
			),
		);
	}

	protected function required_keys(): array {
		return array( 'measurement_id' );
	}

	public function client_config(): array {
		return array( 'measurement_id' => $this->get( 'measurement_id' ) );
	}

	public function send_server( NormalizedEvent $event ): DeliveryResult {
		$api_secret = $this->get( 'api_secret' );
		if ( '' === $api_secret ) {
			return DeliveryResult::failure( __( 'No Measurement Protocol API secret configured.', 'wp-conversion-hub' ) );
		}

		$client_id = (string) ( $event->identity['client_id'] ?? '' );
		if ( '' === $client_id ) {
			$client_id = wp_generate_uuid4();
		}

		$url = add_query_arg(
			array(
				'measurement_id' => $this->get( 'measurement_id' ),
				'api_secret'     => $api_secret,
			),
			self::MP_ENDPOINT
		);

		$body = array(
			'client_id'            => $client_id,
			'timestamp_micros'     => $event->timestamp * 1000000,
			'non_personalized_ads' => false,
			'events'               => array(
				array(
					'name'   => $this->map_event_name( $event->type ),
					'params' => $this->params( $event ),
				),
			),
		);

		return $this->post_json( $url, $body );
	}

	private function map_event_name( string $type ): string {
		$map = array(
			EventType::PURCHASE       => 'purchase',
			EventType::ADD_TO_CART    => 'add_to_cart',
			EventType::BEGIN_CHECKOUT => 'begin_checkout',
			EventType::VIEW_ITEM      => 'view_item',
			EventType::SEARCH         => 'search',
			EventType::LOGIN          => 'login',
			EventType::REGISTER       => 'sign_up',
			EventType::REFUND         => 'refund',
			EventType::FILE_DOWNLOAD  => 'file_download',
		);
		return $map[ $type ] ?? sanitize_key( $type );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function params( NormalizedEvent $event ): array {
		$params = array(
			'engagement_time_msec' => 1,
			'wpch_event_id'        => $event->event_id,
		);

		if ( null !== $event->value ) {
			$params['value'] = $event->value;
		}
		if ( null !== $event->currency ) {
			$params['currency'] = $event->currency;
		}
		if ( EventType::PURCHASE === $event->type && '' !== $event->entity_id ) {
			$params['transaction_id'] = $event->entity_id;
		}
		if ( ! empty( $event->items ) ) {
			$params['items'] = $event->items;
		}
		if ( isset( $event->meta['search_term'] ) ) {
			$params['search_term'] = (string) $event->meta['search_term'];
		}

		return $params;
	}
}
