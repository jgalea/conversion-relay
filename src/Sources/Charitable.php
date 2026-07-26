<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Charitable donation receipt events.
final class Charitable extends AbstractSource {

	public function id(): string {
		return 'charitable';
	}

	public function label(): string {
		return 'Charitable';
	}

	public function is_available(): bool {
		return class_exists( 'Charitable' ) || function_exists( 'charitable' );
	}

	public function supported_events(): array {
		return array( EventType::DONATION );
	}

	protected function hooks(): void {
		add_action( 'charitable_donation_receipt_after', array( $this, 'on_receipt' ) );
	}

	/**
	 * @param mixed $donation Charitable_Donation object when the plugin is loaded.
	 */
	public function on_receipt( $donation ): void {
		if ( ! is_object( $donation ) ) {
			return;
		}

		$entity_id = method_exists( $donation, 'get_donation_id' )
			? (string) $donation->get_donation_id()
			: '';

		$value = method_exists( $donation, 'get_total_donation_amount' )
			? (float) $donation->get_total_donation_amount()
			: null;

		$currency = function_exists( 'charitable_get_currency' )
			? charitable_get_currency()
			: null;

		$this->emit(
			array(
				'type'      => EventType::DONATION,
				'entity_id' => $entity_id,
				'value'     => $value,
				'currency'  => $currency,
			)
		);
	}
}
