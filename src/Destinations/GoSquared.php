<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** GoSquared. Client-side events fired through the site's own GoSquared script. */
final class GoSquared extends AbstractDestination {

	public function id(): string {
		return 'gosquared';
	}

	public function label(): string {
		return 'GoSquared';
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
