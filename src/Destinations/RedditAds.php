<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\EventType;
use WPConversionHub\Event\NormalizedEvent;

// Sends server-side Conversions API events to Reddit Ads.
final class RedditAds extends AbstractDestination {

	public function id(): string {
		return 'reddit';
	}

	public function label(): string {
		return 'Reddit Ads';
	}

	public function transports(): array {
		return array( self::TRANSPORT_SERVER );
	}

	public function consent_category(): string {
		return self::CONSENT_ADS;
	}

	public function tier(): string {
		return self::TIER_PRO;
	}

	public function pro_note(): string {
		return __( 'Server-side Conversions API delivery to Reddit.', 'conversion-relay' );
	}

	public function capabilities(): array {
		return array_merge(
			parent::capabilities(),
			array(
				'revenue'   => true,
				'user_data' => true,
				'dedup'     => true,
			)
		);
	}

	public function settings_fields(): array {
		return array(
			'pixel_id'     => array(
				'label'  => __( 'Pixel ID (a2_xxxxxxxx)', 'conversion-relay' ),
				'type'   => 'text',
				'secret' => false,
			),
			'access_token' => array(
				'label'  => __( 'Access token', 'conversion-relay' ),
				'type'   => 'text',
				'secret' => true,
			),
		);
	}

	protected function required_keys(): array {
		return array( 'pixel_id', 'access_token' );
	}

	public function client_config(): array {
		return array();
	}

	public function send_server( NormalizedEvent $event ): DeliveryResult {
		$access_token = $this->get( 'access_token' );
		if ( '' === $access_token ) {
			return DeliveryResult::failure( 'No Reddit access token configured.' );
		}

		$hashed_user = $this->hashed_user_data( $event );

		$body = array(
			'events' => array(
				array(
					'event_at'       => gmdate( 'c', $event->timestamp ),
					'event_type'     => array(
						'tracking_type' => $this->map( $event->type ),
					),
					'event_metadata' => array_filter(
						array(
							'value_decimal' => $event->value,
							'currency'      => $event->currency,
							'conversion_id' => $event->event_id,
						),
						static fn ( $v ) => null !== $v
					),
					'user'           => array_filter(
						array_merge(
							$hashed_user,
							array(
								'ip_address' => $event->identity['ip'] ?? '',
								'user_agent' => $event->identity['user_agent'] ?? '',
							)
						)
					),
				),
			),
		);

		$url = 'https://ads-api.reddit.com/api/v2.0/conversions/events/' . rawurlencode( $this->get( 'pixel_id' ) );

		return $this->post_json(
			$url,
			$body,
			array( 'Authorization' => 'Bearer ' . $access_token )
		);
	}

	private function map( string $type ): string {
		$map = array(
			EventType::PURCHASE        => 'Purchase',
			EventType::ADD_TO_CART     => 'AddToCart',
			EventType::BEGIN_CHECKOUT  => 'AddToCart',
			EventType::VIEW_ITEM       => 'ViewContent',
			EventType::SEARCH          => 'Search',
			EventType::REGISTER        => 'SignUp',
			EventType::SUBSCRIBE       => 'Purchase',
			EventType::FORM_SUBMISSION => 'Lead',
		);

		return $map[ $type ] ?? 'Custom';
	}
}
