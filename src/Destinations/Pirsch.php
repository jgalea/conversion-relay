<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** Pirsch Analytics. Client-side events fired through the site's own Pirsch Analytics script. */
final class Pirsch extends AbstractDestination {

	public function id(): string {
		return 'pirsch';
	}

	public function label(): string {
		return 'Pirsch Analytics';
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
