<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** WPForms form submission source. */
final class WPForms extends AbstractSource {

	public function id(): string {
		return 'wpforms';
	}

	public function label(): string {
		return 'WPForms';
	}

	public function is_available(): bool {
		return function_exists( 'wpforms' ) || class_exists( 'WPForms\\WPForms' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {
		add_action( 'wpforms_process_complete', array( $this, 'on_complete' ), 10, 4 );
	}

	/**
	 * @param mixed $fields
	 * @param mixed $entry
	 * @param mixed $form_data
	 * @param mixed $entry_id
	 */
	public function on_complete( $fields, $entry, $form_data, $entry_id ): void {
		if ( ! is_array( $form_data ) ) {
			return;
		}

		$settings = is_array( $form_data['settings'] ?? null ) ? $form_data['settings'] : array();

		$this->emit(
			array(
				'type'      => EventType::FORM_SUBMISSION,
				'entity_id' => (string) $entry_id,
				'meta'      => array(
					'form_id'    => $form_data['id'] ?? '',
					'form_title' => $settings['form_title'] ?? '',
				),
			)
		);
	}
}
