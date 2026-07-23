<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\EventType;
use WPConversionHub\Event\NormalizedEvent;

/** Sends server-side Conversions API events to Pinterest Ads. */
final class Pinterest extends AbstractDestination {

	public function id(): string {
		return 'pinterest';
	}

	public function label(): string {
		return 'Pinterest Ads';
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
		return 'Server-side Conversions API delivery to Pinterest.';
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
			'ad_account_id' => array(
				'label'  => 'Ad account ID',
				'type'   => 'text',
				'secret' => false,
			),
			'access_token'  => array(
				'label'  => 'Access token',
				'type'   => 'text',
				'secret' => true,
			),
		);
	}

	protected function required_keys(): array {
		return array( 'ad_account_id', 'access_token' );
	}

	public function client_config(): array {
		return array();
	}

	public function send_server( NormalizedEvent $event ): DeliveryResult {
		$access_token = $this->get( 'access_token' );
		if ( '' === $access_token ) {
			return DeliveryResult::failure( 'No Pinterest access token configured.' );
		}

		$body = array(
			'data' => array(
				array(
					'event_name'    => $this->map( $event->type ),
					'action_source' => 'web',
					'event_time'    => $event->timestamp,
					'event_id'      => $event->event_id,
					'user_data'     => array_filter(
						array_merge(
							$this->hashed_user_data( $event ),
							array(
								'client_ip_address' => $event->identity['ip'] ?? '',
								'client_user_agent' => $event->identity['user_agent'] ?? '',
							)
						)
					),
					'custom_data'   => array_filter(
						array(
							'currency' => $event->currency,
							'value'    => null !== $event->value ? (string) $event->value : null,
						),
						static fn ( $value ): bool => null !== $value && '' !== $value
					),
				),
			),
		);

		$url = 'https://api.pinterest.com/v5/ad_accounts/' . rawurlencode( $this->get( 'ad_account_id' ) ) . '/events';

		return $this->post_json(
			$url,
			$body,
			array( 'Authorization' => 'Bearer ' . $access_token )
		);
	}

	private function map( string $type ): string {
		$map = array(
			EventType::PURCHASE        => 'checkout',
			EventType::ADD_TO_CART     => 'add_to_cart',
			EventType::BEGIN_CHECKOUT  => 'checkout',
			EventType::VIEW_ITEM       => 'page_visit',
			EventType::SEARCH          => 'search',
			EventType::REGISTER        => 'signup',
			EventType::FORM_SUBMISSION => 'lead',
		);

		return $map[ $type ] ?? 'custom';
	}
}
