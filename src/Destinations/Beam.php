<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** Beam Analytics. Client-side events fired through the site's own Beam Analytics script. */
final class Beam extends AbstractDestination {

	public function id(): string {
		return 'beam';
	}

	public function label(): string {
		return 'Beam Analytics';
	}

	public function transports(): array {
		return array( self::TRANSPORT_CLIENT );
	}

	public function is_configured(): bool {
		return true;
	}

	public function settings_fields(): array {
		return array();
	}
}
