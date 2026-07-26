<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** Microsoft Clarity. Client-side tags fired through the site's Clarity snippet. */
final class Clarity extends AbstractDestination {

	public function id(): string {
		return 'clarity';
	}

	public function label(): string {
		return 'Microsoft Clarity';
	}

	public function transports(): array {
		return array( self::TRANSPORT_CLIENT );
	}

	public function settings_fields(): array {
		return array(
			'project_id' => array(
				'label'  => __( 'Project ID', 'conversion-relay' ),
				'type'   => 'text',
				'secret' => false,
			),
		);
	}

	protected function required_keys(): array {
		return array( 'project_id' );
	}

	public function client_config(): array {
		return array( 'project_id' => $this->get( 'project_id' ) );
	}
}
