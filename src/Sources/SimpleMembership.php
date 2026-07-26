<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Simple Membership front-end registration completions.
final class SimpleMembership extends AbstractSource {

	public function id(): string {
		return 'simplemembership';
	}

	public function label(): string {
		return 'Simple Membership';
	}

	public function is_available(): bool {
		return class_exists( 'SimpleWpMembership' ) || defined( 'SIMPLE_WP_MEMBERSHIP_VER' );
	}

	public function supported_events(): array {
		return array( EventType::REGISTER );
	}

	protected function hooks(): void {
		add_action( 'swpm_front_end_registration_complete_user_data', array( $this, 'on_register' ) );
	}

	/**
	 * @param mixed $fields Registration field data from Simple Membership.
	 */
	public function on_register( $fields ): void {
		if ( ! is_array( $fields ) ) {
			return;
		}

		$this->emit(
			array(
				'type'      => EventType::REGISTER,
				'entity_id' => isset( $fields['member_id'] ) ? (string) $fields['member_id'] : '',
			)
		);
	}
}
