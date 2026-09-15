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
			'site_id'      => array(
				'label'  => __( 'Site ID (ABCDEFGH)', 'conversion-relay' ),
				'type'   => 'text',
				'secret' => false,
			),
			'label_prefix' => array(
				'label'  => __( 'Event label prefix', 'conversion-relay' ),
				'type'   => 'text',
				'secret' => false,
				'help'   => __( 'Prepended to every event name sent to Fathom. Use it to keep these events on their own rows when another tool already reports conversions to the same site.', 'conversion-relay' ),
			),
		);
	}

	protected function required_keys(): array {
		return array( 'site_id' );
	}

	public function client_config(): array {
		return array(
			'site_id'      => $this->get( 'site_id' ),
			'label_prefix' => $this->get( 'label_prefix' ),
		);
	}
}
