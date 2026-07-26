<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** Simple Analytics. Client-side events fired through the site's own Simple Analytics script. */
final class SimpleAnalytics extends AbstractDestination {

	public function id(): string {
		return 'simpleanalytics';
	}

	public function label(): string {
		return 'Simple Analytics';
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
