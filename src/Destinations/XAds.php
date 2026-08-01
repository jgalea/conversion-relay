<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** X (Twitter) Ads. Client-side website tag (twq). */
final class XAds extends AbstractDestination {

	public function id(): string {
		return 'x';
	}

	public function label(): string {
		return 'X (Twitter) Ads';
	}

	public function consent_category(): string {
		return self::CONSENT_ADS;
	}

	public function transports(): array {
		return array( self::TRANSPORT_CLIENT );
	}

	public function settings_fields(): array {
		return array(
			'pixel_id' => array(
				'label'  => __( 'Pixel ID', 'conversion-relay' ),
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
}
