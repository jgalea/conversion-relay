<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Paid Memberships Pro checkout subscriptions.
final class PaidMembershipsPro extends AbstractSource {

	public function id(): string {
		return 'pmpro';
	}

	public function label(): string {
		return 'Paid Memberships Pro';
	}

	public function is_available(): bool {
		return defined( 'PMPRO_VERSION' ) || function_exists( 'pmpro_getLevel' );
	}

	public function supported_events(): array {
		return array( EventType::SUBSCRIBE );
	}

	protected function hooks(): void {
		add_action( 'pmpro_after_checkout', array( $this, 'on_checkout' ), 10, 2 );
	}

	/**
	 * @param mixed $user_id
	 * @param mixed $morder
	 */
	public function on_checkout( $user_id, $morder ): void {
		$this->emit(
			array(
				'type'      => EventType::SUBSCRIBE,
				'entity_id' => is_object( $morder ) && isset( $morder->id ) ? (string) $morder->id : (string) $user_id,
				'value'     => is_object( $morder ) && isset( $morder->total ) ? (float) $morder->total : null,
				'currency'  => function_exists( 'pmpro_get_currency' ) ? pmpro_get_currency() : null,
			)
		);
	}
}
