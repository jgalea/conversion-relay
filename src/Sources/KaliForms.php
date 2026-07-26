<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** Kali Forms forms, tracked client-side on submit (no reliable server hook). */
final class KaliForms extends AbstractSource {

	public function id(): string {
		return 'kaliforms';
	}

	public function label(): string {
		return 'Kali Forms';
	}

	public function is_available(): bool {
		return defined( 'KALIFORMS_PLUGIN_VERSION' ) || class_exists( 'KaliForms' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {}

	public function client_selectors(): array {
		return array( 'form.kaliforms-form' );
	}
}
