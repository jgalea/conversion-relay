<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** Fluent Forms submission source. */
final class FluentForms extends AbstractSource {

	public function id(): string {
		return 'fluentforms';
	}

	public function label(): string {
		return 'Fluent Forms';
	}

	public function is_available(): bool {
		return defined( 'FLUENTFORM_VERSION' ) || function_exists( 'wpFluentForm' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {
		add_action( 'fluentform/submission_inserted', array( $this, 'on_insert' ), 10, 3 );
	}

	/**
	 * @param mixed $entryId
	 * @param mixed $formData
	 * @param mixed $form
	 */
	public function on_insert( $entryId, $formData, $form ): void {
		$this->emit(
			array(
				'type'      => EventType::FORM_SUBMISSION,
				'entity_id' => (string) $entryId,
				'meta'      => array(
					'form_id'    => is_object( $form ) && isset( $form->id ) ? $form->id : '',
					'form_title' => is_object( $form ) && isset( $form->title ) ? $form->title : '',
				),
			)
		);
	}
}
