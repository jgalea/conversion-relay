<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** weForms forms, tracked client-side on submit (no reliable server hook). */
final class WeForms extends AbstractSource {

	public function id(): string {
		return 'weforms';
	}

	public function label(): string {
		return 'weForms';
	}

	public function is_available(): bool {
		return function_exists( 'weForms' ) || class_exists( 'WeForms' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {}

	public function client_selectors(): array {
		return array( 'form.wpuf-form' );
	}
}
