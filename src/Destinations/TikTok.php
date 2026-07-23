<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\EventType;
use WPConversionHub\Event\NormalizedEvent;

/** Sends server-side Events API events to TikTok Ads. */
final class TikTok extends AbstractDestination {

	public function id(): string {
		return 'tiktok';
	}

	public function label(): string {
		return 'TikTok Ads';
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
		return 'Server-side Events API delivery to TikTok.';
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
			'pixel_code'      => array(
				'label'  => 'Pixel code',
				'type'   => 'text',
				'secret' => false,
			),
			'access_token'    => array(
				'label'  => 'Access token',
				'type'   => 'text',
				'secret' => true,
			),
			'test_event_code' => array(
				'label'  => 'Test event code',
				'type'   => 'text',
				'secret' => false,
			),
		);
	}

	protected function required_keys(): array {
		return array( 'pixel_code', 'access_token' );
	}

	public function client_config(): array {
		return array();
	}

	public function send_server( NormalizedEvent $event ): DeliveryResult {
		$access_token = $this->get( 'access_token' );
		if ( '' === $access_token ) {
			return DeliveryResult::failure( 'No TikTok access token configured.' );
		}

		$body = array(
			'event_source'    => 'web',
			'event_source_id' => $this->get( 'pixel_code' ),
			'data'            => array(
				array(
					'event'      => $this->map( $event->type ),
					'event_time' => $event->timestamp,
					'event_id'   => $event->event_id,
					'user'       => array_filter(
						array_merge(
							$this->hashed_user_data( $event ),
							array(
								'ip'         => $event->identity['ip'] ?? '',
								'user_agent' => $event->identity['user_agent'] ?? '',
							)
						)
					),
					'properties' => array_filter(
						array(
							'value'    => $event->value,
							'currency' => $event->currency,
						),
						static fn ( $value ): bool => null !== $value
					),
					'page'       => array( 'url' => $event->url ),
				),
			),
		);

		$test_event_code = $this->get( 'test_event_code' );
		if ( '' !== $test_event_code ) {
			$body['test_event_code'] = $test_event_code;
		}

		return $this->post_json(
			'https://business-api.tiktok.com/open_api/v1.3/event/track/',
			$body,
			array( 'Access-Token' => $access_token )
		);
	}

	private function map( string $type ): string {
		$map = array(
			EventType::PURCHASE        => 'CompletePayment',
			EventType::ADD_TO_CART     => 'AddToCart',
			EventType::BEGIN_CHECKOUT  => 'InitiateCheckout',
			EventType::VIEW_ITEM       => 'ViewContent',
			EventType::SEARCH          => 'Search',
			EventType::REGISTER        => 'CompleteRegistration',
			EventType::SUBSCRIBE       => 'Subscribe',
			EventType::FORM_SUBMISSION => 'SubmitForm',
		);

		return $map[ $type ] ?? 'CustomEvent';
	}
}
