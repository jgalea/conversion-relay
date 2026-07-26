<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\EventType;
use WPConversionHub\Event\NormalizedEvent;

// Sends browser and Conversions API events to Meta Ads.
final class MetaAds extends AbstractDestination {

	public function id(): string {
		return 'meta';
	}

	public function label(): string {
		return 'Meta (Facebook) Ads';
	}

	public function transports(): array {
		$transports = array( self::TRANSPORT_CLIENT );
		if ( '' !== $this->get( 'access_token' ) ) {
			$transports[] = self::TRANSPORT_SERVER;
		}
		return $transports;
	}

	public function consent_category(): string {
		return self::CONSENT_ADS;
	}

	public function tier(): string {
		return self::TIER_PRO;
	}

	public function pro_note(): string {
		return __( 'Pixel plus server-side Conversions API, with ad-blocker-proof attribution.', 'conversion-relay' );
	}

	public function capabilities(): array {
		return array_merge(
			parent::capabilities(),
			array(
				'items'     => false,
				'revenue'   => true,
				'user_data' => true,
				'dedup'     => true,
			)
		);
	}

	public function settings_fields(): array {
		return array(
			'pixel_id'        => array(
				'label'  => __( 'Pixel ID', 'conversion-relay' ),
				'type'   => 'text',
				'secret' => false,
			),
			'access_token'    => array(
				'label'  => __( 'Access token', 'conversion-relay' ),
				'type'   => 'text',
				'secret' => true,
			),
			'test_event_code' => array(
				'label'  => __( 'Test event code', 'conversion-relay' ),
				'type'   => 'text',
				'secret' => false,
			),
		);
	}

	protected function required_keys(): array {
		return array( 'pixel_id' );
	}

	public function client_config(): array {
		return array( 'pixel_id' => $this->get( 'pixel_id' ) );
	}

	public function send_server( NormalizedEvent $event ): DeliveryResult {
		$access_token = $this->get( 'access_token' );
		if ( '' === $access_token ) {
			return DeliveryResult::failure( 'No Meta access token configured.' );
		}

		$user_data = array_merge(
			$this->hashed_user_data( $event ),
			$this->present_values(
				array(
					'client_ip_address' => $event->identity['ip'] ?? '',
					'client_user_agent' => $event->identity['user_agent'] ?? '',
					'fbp'               => $event->identity['fbp'] ?? '',
					'fbc'               => $event->identity['fbc'] ?? '',
				)
			)
		);

		$body = array(
			'data' => array(
				array(
					'event_name'       => $this->map_event_name( $event->type ),
					'event_time'       => $event->timestamp,
					'event_id'         => $event->event_id,
					'action_source'    => 'website',
					'event_source_url' => $event->url,
					'user_data'        => $user_data,
					'custom_data'      => $this->present_values(
						array(
							'value'    => $event->value,
							'currency' => $event->currency,
						)
					),
				),
			),
		);

		$test_event_code = $this->get( 'test_event_code' );
		if ( '' !== $test_event_code ) {
			$body['test_event_code'] = $test_event_code;
		}

		$url = 'https://graph.facebook.com/v19.0/' . rawurlencode( $this->get( 'pixel_id' ) ) . '/events?access_token=' . rawurlencode( $access_token );
		return $this->post_json( $url, $body );
	}

	private function map_event_name( string $type ): string {
		$map = array(
			EventType::PURCHASE        => 'Purchase',
			EventType::ADD_TO_CART     => 'AddToCart',
			EventType::BEGIN_CHECKOUT  => 'InitiateCheckout',
			EventType::VIEW_ITEM       => 'ViewContent',
			EventType::SEARCH          => 'Search',
			EventType::REGISTER        => 'CompleteRegistration',
			EventType::SUBSCRIBE       => 'Subscribe',
			EventType::FORM_SUBMISSION => 'Lead',
			EventType::DONATION        => 'Donate',
		);
		return $map[ $type ] ?? 'CustomEvent';
	}

	/**
	 * @param array<string,mixed> $values
	 * @return array<string,mixed>
	 */
	private function present_values( array $values ): array {
		return array_filter(
			$values,
			static function ( $value ): bool {
				return null !== $value && '' !== $value;
			}
		);
	}
}
