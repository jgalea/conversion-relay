<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** Divi forms, tracked client-side on submit (no reliable server hook). */
final class Divi extends AbstractSource {

	public function id(): string {
		return 'divi';
	}

	public function label(): string {
		return 'Divi';
	}

	public function is_available(): bool {
		return function_exists( 'et_setup_theme' ) || class_exists( 'ET_Builder_Module' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {}

	public function client_selectors(): array {
		return array( '.et_pb_contact_form_container form' );
	}
}
