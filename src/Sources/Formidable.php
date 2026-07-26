<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Formidable Forms entry submissions.
final class Formidable extends AbstractSource {

	public function id(): string {
		return 'formidable';
	}

	public function label(): string {
		return 'Formidable Forms';
	}

	public function is_available(): bool {
		return class_exists( 'FrmHooksController' ) || class_exists( 'FrmForm' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {
		add_action( 'frm_after_create_entry', array( $this, 'on_entry' ), 30, 2 );
	}

	/**
	 * @param mixed $entry_id
	 * @param mixed $form_id
	 */
	public function on_entry( $entry_id, $form_id ): void {
		$this->emit(
			array(
				'type'      => EventType::FORM_SUBMISSION,
				'entity_id' => (string) $entry_id,
				'meta'      => array(
					'form_id' => (string) $form_id,
				),
			)
		);
	}
}
