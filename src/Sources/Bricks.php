<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** Bricks forms, tracked client-side on submit (no reliable server hook). */
final class Bricks extends AbstractSource {

	public function id(): string {
		return 'bricks';
	}

	public function label(): string {
		return 'Bricks';
	}

	public function is_available(): bool {
		return defined( 'BRICKS_VERSION' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {}

	public function client_selectors(): array {
		return array( 'form.brxe-form' );
	}
}
