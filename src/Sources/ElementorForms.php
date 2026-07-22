<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** Elementor Pro Forms submission source. */
final class ElementorForms extends AbstractSource {

	public function id(): string {
		return 'elementor';
	}

	public function label(): string {
		return 'Elementor Forms';
	}

	public function is_available(): bool {
		return did_action( 'elementor/loaded' ) > 0 || class_exists( 'ElementorPro\\Plugin' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {
		add_action( 'elementor_pro/forms/new_record', array( $this, 'on_record' ), 10, 2 );
	}

	/**
	 * @param mixed $record
	 * @param mixed $handler
	 */
	public function on_record( $record, $handler = null ): void {
		if ( ! is_object( $record ) ) {
			return;
		}

		$settings = method_exists( $record, 'get_form_settings' ) ? $record->get_form_settings( 'form_name' ) : '';

		$this->emit(
			array(
				'type'      => EventType::FORM_SUBMISSION,
				'entity_id' => '',
				'meta'      => array(
					'form_name' => is_string( $settings ) ? $settings : '',
				),
			)
		);
	}
}
