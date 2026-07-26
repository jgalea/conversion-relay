<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** Breakdance forms, tracked client-side on submit (no reliable server hook). */
final class Breakdance extends AbstractSource {

	public function id(): string {
		return 'breakdance';
	}

	public function label(): string {
		return 'Breakdance';
	}

	public function is_available(): bool {
		return defined( '__BREAKDANCE_VERSION' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {}

	public function client_selectors(): array {
		return array( '.breakdance-form form' );
	}
}
