<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** Contact Form 7 form submission source. */
final class ContactForm7 extends AbstractSource {

	public function id(): string {
		return 'cf7';
	}

	public function label(): string {
		return 'Contact Form 7';
	}

	public function is_available(): bool {
		return class_exists( 'WPCF7' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {
		add_action( 'wpcf7_mail_sent', array( $this, 'on_sent' ) );
	}

	/**
	 * @param mixed $contact_form
	 */
	public function on_sent( $contact_form ): void {
		if ( ! is_object( $contact_form ) ) {
			return;
		}

		$entity_id = method_exists( $contact_form, 'id' ) ? (string) $contact_form->id() : '';
		$title     = method_exists( $contact_form, 'title' ) ? $contact_form->title() : '';

		$this->emit(
			array(
				'type'      => EventType::FORM_SUBMISSION,
				'entity_id' => $entity_id,
				'meta'      => array(
					'form_id'    => $entity_id,
					'form_title' => is_string( $title ) ? $title : '',
				),
			)
		);
	}
}
