<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits WS Form submissions.
final class WSForm extends AbstractSource {

	public function id(): string {
		return 'wsform';
	}

	public function label(): string {
		return 'WS Form';
	}

	public function is_available(): bool {
		return class_exists( 'WS_Form' ) || defined( 'WS_FORM_VERSION' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {
		add_action( 'wsf_submit_post_complete', array( $this, 'on_submit' ), 10, 2 );
	}

	/**
	 * @param mixed $form
	 * @param mixed $submit
	 */
	public function on_submit( $form, $submit ): void {
		$this->emit(
			array(
				'type'      => EventType::FORM_SUBMISSION,
				'entity_id' => is_object( $submit ) && isset( $submit->id ) ? (string) $submit->id : '',
				'meta'      => array(
					'form_id' => is_object( $form ) && isset( $form->id ) ? $form->id : '',
				),
			)
		);
	}
}
