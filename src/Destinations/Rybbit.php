<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** Rybbit. Client-side events fired through the site's own Rybbit script. */
final class Rybbit extends AbstractDestination {

	public function id(): string {
		return 'rybbit';
	}

	public function label(): string {
		return 'Rybbit';
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
