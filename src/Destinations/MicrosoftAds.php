<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** Microsoft Advertising (UET). Client-side conversion tag. */
final class MicrosoftAds extends AbstractDestination {

	public function id(): string {
		return 'microsoft';
	}

	public function label(): string {
		return 'Microsoft Ads';
	}

	public function consent_category(): string {
		return self::CONSENT_ADS;
	}

	public function tier(): string {
		return self::TIER_PRO;
	}

	public function pro_note(): string {
		return __( 'Microsoft Advertising UET conversion tracking.', 'conversion-relay' );
	}

	public function transports(): array {
		return array( self::TRANSPORT_CLIENT );
	}

	public function settings_fields(): array {
		return array(
			'tag_id' => array(
				'label'  => __( 'UET Tag ID', 'conversion-relay' ),
				'type'   => 'text',
				'secret' => false,
			),
		);
	}

	protected function required_keys(): array {
		return array( 'tag_id' );
	}

	public function client_config(): array {
		return array( 'tag_id' => $this->get( 'tag_id' ) );
	}
}
