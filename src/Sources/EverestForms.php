<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Everest Forms submissions.
final class EverestForms extends AbstractSource {

	public function id(): string {
		return 'everestforms';
	}

	public function label(): string {
		return 'Everest Forms';
	}

	public function is_available(): bool {
		return class_exists( 'EVF' ) || function_exists( 'evf' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {
		add_action( 'everest_forms_complete_entry_save', array( $this, 'on_save' ), 10, 4 );
	}

	/**
	 * @param mixed $entry_id
	 * @param mixed $form_fields
	 * @param mixed $entry
	 * @param mixed $form_id
	 */
	public function on_save( $entry_id, $form_fields, $entry, $form_id ): void {
		$this->emit(
			array(
				'type'      => EventType::FORM_SUBMISSION,
				'entity_id' => (string) $entry_id,
				'meta'      => array( 'form_id' => (string) $form_id ),
			)
		);
	}
}
