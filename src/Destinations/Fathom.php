<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** Fathom Analytics client-only destination (window.fathom). */
final class Fathom extends AbstractDestination {

	public function id(): string {
		return 'fathom';
	}

	public function label(): string {
		return 'Fathom Analytics';
	}

	public function transports(): array {
		return array( self::TRANSPORT_CLIENT );
	}

	public function capabilities(): array {
		return array_merge(
			parent::capabilities(),
			array( 'revenue' => true )
		);
	}

	public function settings_fields(): array {
		return array(
			'site_id' => array(
				'label'  => 'Site ID (ABCDEFGH)',
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
