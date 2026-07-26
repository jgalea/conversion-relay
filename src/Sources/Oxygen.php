<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** Oxygen forms, tracked client-side on submit (no reliable server hook). */
final class Oxygen extends AbstractSource {

	public function id(): string {
		return 'oxygen';
	}

	public function label(): string {
		return 'Oxygen';
	}

	public function is_available(): bool {
		return defined( 'CT_VERSION' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {}

	public function client_selectors(): array {
		return array( 'form.oxy-dynamic-form' );
	}
}
