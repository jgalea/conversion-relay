<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Ninja Forms submissions after successful form processing.
final class NinjaForms extends AbstractSource {

	public function id(): string {
		return 'ninjaforms';
	}

	public function label(): string {
		return 'Ninja Forms';
	}

	public function is_available(): bool {
		return class_exists( 'Ninja_Forms' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {
		add_action( 'ninja_forms_after_submission', array( $this, 'on_submit' ) );
	}

	/**
	 * @param mixed $form_data
	 */
	public function on_submit( $form_data ): void {
		if ( ! is_array( $form_data ) ) {
			return;
		}

		$this->emit(
			array(
				'type'      => EventType::FORM_SUBMISSION,
				'entity_id' => isset( $form_data['form_id'] ) ? (string) $form_data['form_id'] : '',
				'meta'      => array(
					'form_id' => $form_data['form_id'] ?? '',
				),
			)
		);
	}
}
