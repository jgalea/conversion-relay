<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** Beaver Builder forms, tracked client-side on submit (no reliable server hook). */
final class BeaverBuilder extends AbstractSource {

	public function id(): string {
		return 'beaverbuilder';
	}

	public function label(): string {
		return 'Beaver Builder';
	}

	public function is_available(): bool {
		return class_exists( 'FLBuilder' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {}

	public function client_selectors(): array {
		return array( '.fl-module-contact-form form' );
	}
}
