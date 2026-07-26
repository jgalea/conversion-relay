<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Restrict Content Pro subscription registrations.
final class RestrictContentPro extends AbstractSource {

	public function id(): string {
		return 'rcp';
	}

	public function label(): string {
		return 'Restrict Content Pro';
	}

	public function is_available(): bool {
		return class_exists( 'RCP_Requirements_Check' ) || function_exists( 'rcp_get_subscription' );
	}

	public function supported_events(): array {
		return array( EventType::SUBSCRIBE );
	}

	protected function hooks(): void {
		add_action( 'rcp_successful_registration', array( $this, 'on_register' ), 10, 3 );
	}

	/**
	 * @param mixed $member_id
	 * @param mixed $user_id
	 * @param mixed $rcp_payment
	 */
	public function on_register( $member_id, $user_id, $rcp_payment ): void {
		$this->emit(
			array(
				'type'      => EventType::SUBSCRIBE,
				'entity_id' => is_scalar( $member_id ) ? (string) $member_id : '',
				'meta'      => array(
					'user_id' => is_scalar( $user_id ) ? $user_id : '',
				),
			)
		);
	}
}
