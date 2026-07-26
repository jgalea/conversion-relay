<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** Usermaven. Client-side events fired through the site's own Usermaven script. */
final class Usermaven extends AbstractDestination {

	public function id(): string {
		return 'usermaven';
	}

	public function label(): string {
		return 'Usermaven';
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
