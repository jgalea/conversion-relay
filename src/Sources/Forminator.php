<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** Forminator forms, tracked client-side on submit (no reliable server hook). */
final class Forminator extends AbstractSource {

	public function id(): string {
		return 'forminator';
	}

	public function label(): string {
		return 'Forminator';
	}

	public function is_available(): bool {
		return class_exists( 'Forminator' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {}

	public function client_selectors(): array {
		return array( 'form.forminator-ui' );
	}
}
