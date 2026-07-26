<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits WP Simple Pay successful payment events.
final class WPSimplePay extends AbstractSource {

	public function id(): string {
		return 'wpsimplepay';
	}

	public function label(): string {
		return 'WP Simple Pay';
	}

	public function is_available(): bool {
		return class_exists( 'SimplePay\\Core\\SimplePay' ) || function_exists( 'simpay_get_form' );
	}

	public function supported_events(): array {
		return array( EventType::PURCHASE );
	}

	protected function hooks(): void {
		add_action( 'simpay_webhook_payment_intent_succeeded', array( $this, 'on_paid' ), 10, 2 );
	}

	/**
	 * @param mixed $event
	 * @param mixed $form
	 */
	public function on_paid( $event, $form ): void {
		if ( ! is_object( $event ) ) {
			return;
		}

		$payment = isset( $event->data->object ) && is_object( $event->data->object ) ? $event->data->object : null;

		$this->emit(
			array(
				'type'      => EventType::PURCHASE,
				'entity_id' => null !== $payment && isset( $payment->id ) ? (string) $payment->id : '',
				'value'     => null !== $payment && isset( $payment->amount ) ? ( (float) $payment->amount ) / 100 : null,
				'currency'  => null !== $payment && isset( $payment->currency ) ? strtoupper( (string) $payment->currency ) : null,
			)
		);
	}
}
