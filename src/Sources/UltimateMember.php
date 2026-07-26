<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Ultimate Member registration events after auto-login.
final class UltimateMember extends AbstractSource {

	public function id(): string {
		return 'ultimatemember';
	}

	public function label(): string {
		return 'Ultimate Member';
	}

	public function is_available(): bool {
		return function_exists( 'UM' ) || defined( 'um_plugin' );
	}

	public function supported_events(): array {
		return array( EventType::REGISTER );
	}

	protected function hooks(): void {
		add_action( 'um_registration_after_auto_login', array( $this, 'on_register' ) );
	}

	/**
	 * @param mixed $user_id
	 */
	public function on_register( $user_id ): void {
		$this->emit(
			array(
				'type'      => EventType::REGISTER,
				'entity_id' => (string) $user_id,
			)
		);
	}
}
