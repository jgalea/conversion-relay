<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** Hotjar. Client-side events fired through the site's Hotjar snippet. */
final class Hotjar extends AbstractDestination {

	public function id(): string {
		return 'hotjar';
	}

	public function label(): string {
		return 'Hotjar';
	}

	public function transports(): array {
		return array( self::TRANSPORT_CLIENT );
	}

	public function settings_fields(): array {
		return array(
			'site_id' => array(
				'label'  => __( 'Site ID', 'conversion-relay' ),
				'type'   => 'text',
				'secret' => false,
			),
		);
	}

	protected function required_keys(): array {
		return array( 'site_id' );
	}

	public function client_config(): array {
		return array( 'site_id' => $this->get( 'site_id' ) );
	}
}
