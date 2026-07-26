<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** Google Tag Manager. Pushes each conversion into the dataLayer for routing in GTM. */
final class GoogleTagManager extends AbstractDestination {

	public function id(): string {
		return 'gtm';
	}

	public function label(): string {
		return 'Google Tag Manager';
	}

	public function transports(): array {
		return array( self::TRANSPORT_CLIENT );
	}

	public function capabilities(): array {
		return array_merge(
			parent::capabilities(),
			array(
				'items'   => true,
				'revenue' => true,
			)
		);
	}

	public function settings_fields(): array {
		return array(
			'container_id' => array(
				'label'  => __( 'Container ID (GTM-XXXXXXX)', 'conversion-relay' ),
				'type'   => 'text',
				'secret' => false,
			),
		);
	}

	protected function required_keys(): array {
		return array( 'container_id' );
	}

	public function client_config(): array {
		return array( 'container_id' => $this->get( 'container_id' ) );
	}
}
