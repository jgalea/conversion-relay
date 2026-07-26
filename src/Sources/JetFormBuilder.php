<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** JetFormBuilder forms, tracked client-side on submit (no reliable server hook). */
final class JetFormBuilder extends AbstractSource {

	public function id(): string {
		return 'jetformbuilder';
	}

	public function label(): string {
		return 'JetFormBuilder';
	}

	public function is_available(): bool {
		return function_exists( 'jet_form_builder' ) || class_exists( 'Jet_Form_Builder\\Plugin' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {}

	public function client_selectors(): array {
		return array( '.jet-form-builder' );
	}
}
