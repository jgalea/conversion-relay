<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** Swetrix. Client-side events fired through the site's own Swetrix script. */
final class Swetrix extends AbstractDestination {

	public function id(): string {
		return 'swetrix';
	}

	public function label(): string {
		return 'Swetrix';
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
