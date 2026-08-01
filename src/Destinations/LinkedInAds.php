<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\NormalizedEvent;

// Sends server-side Conversion API events to LinkedIn Ads.
final class LinkedInAds extends AbstractDestination {

	public function id(): string {
		return 'linkedin';
	}

	public function label(): string {
		return 'LinkedIn Ads';
	}

	public function transports(): array {
		return array( self::TRANSPORT_SERVER );
	}

	public function consent_category(): string {
		return self::CONSENT_ADS;
	}

	public function capabilities(): array {
		return array_merge(
			parent::capabilities(),
			array(
				'revenue'   => true,
				'user_data' => true,
			)
		);
	}

	public function settings_fields(): array {
		return array(
			'conversion_urn' => array(
				'label'  => 'Conversion URN (urn:lla:llaPartnerConversion:123456)',
				'type'   => 'text',
				'secret' => false,
			),
			'access_token'   => array(
				'label'  => 'Access token',
				'type'   => 'text',
				'secret' => true,
			),
		);
	}

	protected function required_keys(): array {
		return array( 'conversion_urn', 'access_token' );
	}

	public function client_config(): array {
		return array();
	}

	public function send_server( NormalizedEvent $event ): DeliveryResult {
		$access_token = $this->get( 'access_token' );
		if ( '' === $access_token ) {
			return DeliveryResult::failure( 'No LinkedIn access token configured.' );
		}

		$hashed = $this->hashed_user_data( $event );
		$body   = array_filter(
			array(
				'conversion'           => $this->get( 'conversion_urn' ),
				'conversionHappenedAt' => $event->timestamp * 1000,
				'conversionValue'      => null !== $event->value ? array(
					'currencyCode' => $event->currency ?: 'USD',
					'amount'       => (string) $event->value,
				) : null,
				'user'                 => array(
					'userIds' => array_values(
						array_filter(
							array(
								isset( $hashed['email'] ) ? array(
									'idType'  => 'SHA256_EMAIL',
									'idValue' => $hashed['email'],
								) : null,
							)
						)
					),
				),
			),
			static function ( $value ): bool {
				return null !== $value;
			}
		);

		return $this->post_json(
			'https://api.linkedin.com/rest/conversionEvents',
			$body,
			array(
				'Authorization'             => 'Bearer ' . $access_token,
				'LinkedIn-Version'          => '202401',
				'X-Restli-Protocol-Version' => '2.0.0',
			)
		);
	}
}
