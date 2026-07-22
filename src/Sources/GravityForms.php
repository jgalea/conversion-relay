<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Gravity Forms submissions after entries are saved.
final class GravityForms extends AbstractSource {

	public function id(): string {
		return 'gravityforms';
	}

	public function label(): string {
		return 'Gravity Forms';
	}

	public function is_available(): bool {
		return class_exists( 'GFForms' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {
		add_action( 'gform_after_submission', array( $this, 'on_submit' ), 10, 2 );
	}

	/**
	 * @param array<string,mixed> $entry
	 * @param array<string,mixed> $form
	 */
	public function on_submit( array $entry, array $form ): void {
		$this->emit(
			array(
				'type'      => EventType::FORM_SUBMISSION,
				'entity_id' => (string) ( $entry['id'] ?? '' ),
				'meta'      => array(
					'form_id'    => $form['id'] ?? '',
					'form_title' => $form['title'] ?? '',
				),
			)
		);
	}
}
