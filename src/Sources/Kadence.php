<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** Kadence Blocks forms, tracked client-side on submit (no reliable server hook). */
final class Kadence extends AbstractSource {

	public function id(): string {
		return 'kadence';
	}

	public function label(): string {
		return 'Kadence Blocks';
	}

	public function is_available(): bool {
		return class_exists( 'Kadence_Blocks_Frontend' ) || function_exists( 'kadence_blocks_init' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {}

	public function client_selectors(): array {
		return array( '.wp-block-kadence-form form' );
	}
}
