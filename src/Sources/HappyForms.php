<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** HappyForms forms, tracked client-side on submit (no reliable server hook). */
final class HappyForms extends AbstractSource {

	public function id(): string {
		return 'happyforms';
	}

	public function label(): string {
		return 'HappyForms';
	}

	public function is_available(): bool {
		return function_exists( 'happyforms' ) || class_exists( 'HappyForms' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {}

	public function client_selectors(): array {
		return array( 'form.happyforms-form' );
	}
}
