<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

// Provides client-side Google Ads conversion configuration.
final class GoogleAds extends AbstractDestination {

	public function id(): string {
		return 'google_ads';
	}

	public function label(): string {
		return 'Google Ads';
	}

	public function transports(): array {
		return array( self::TRANSPORT_CLIENT );
	}

	public function consent_category(): string {
		return self::CONSENT_ADS;
	}

	public function capabilities(): array {
		return array_merge(
			parent::capabilities(),
			array(
				'revenue' => true,
				'dedup'   => true,
			)
		);
	}

	public function settings_fields(): array {
		$fields = array(
			'conversion_id'    => array(
				'label'  => __( 'Conversion ID (AW-XXXXXXXXX)', 'conversion-relay' ),
				'type'   => 'text',
				'secret' => false,
			),
			'conversion_label' => array(
				'label'  => __( 'Purchase conversion label', 'conversion-relay' ),
				'type'   => 'text',
				'secret' => false,
			),
		);

		foreach ( self::conversion_types() as $type => $label ) {
			$fields[ 'label_' . $type ] = array(
				/* translators: %s: event name, e.g. "Form submission". */
				'label'  => sprintf( __( '%s conversion label', 'conversion-relay' ), $label ),
				'type'   => 'text',
				'secret' => false,
				'help'   => __( 'Tick the event under "Send these events" for it to count as a conversion.', 'conversion-relay' ),
			);
		}

		return $fields;
	}

	protected function required_keys(): array {
		return array( 'conversion_id' );
	}

	public function client_config(): array {
		return array(
			'conversion_id'    => $this->get( 'conversion_id' ),
			'conversion_label' => $this->get( 'conversion_label' ),
			'labels'           => $this->per_type_values( 'label_' ),
		);
	}
}
