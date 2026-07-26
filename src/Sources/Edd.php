<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Easy Digital Downloads purchase and checkout events.
final class Edd extends AbstractSource {

	public function id(): string {
		return 'edd';
	}

	public function label(): string {
		return 'Easy Digital Downloads';
	}

	public function is_available(): bool {
		return function_exists( 'EDD' ) || class_exists( 'Easy_Digital_Downloads' );
	}

	public function supported_events(): array {
		return array( EventType::PURCHASE, EventType::BEGIN_CHECKOUT, EventType::REFUND );
	}

	protected function hooks(): void {
		add_action( 'edd_complete_purchase', array( $this, 'on_complete' ) );
		// Catches recurring renewals, which reach a paid status without firing
		// edd_complete_purchase. The deterministic event_id dedupes any overlap
		// with the initial purchase above.
		add_action( 'edd_update_payment_status', array( $this, 'on_status' ), 10, 3 );
		add_action( 'template_redirect', array( $this, 'maybe_begin_checkout' ) );
		add_action( 'edd_refund_order', array( $this, 'on_refund' ), 10, 2 );
	}

	public function on_complete( int $payment_id ): void {
		$this->emit_purchase( $payment_id );
	}

	/**
	 * @param int    $payment_id
	 * @param string $new_status
	 * @param string $old_status
	 */
	public function on_status( int $payment_id, string $new_status = '', string $old_status = '' ): void {
		if ( ! in_array( $new_status, array( 'complete', 'publish', 'edd_subscription' ), true ) ) {
			return;
		}
		$this->emit_purchase( $payment_id );
	}

	public function on_refund( int $order_id, int $refund_id = 0 ): void {
		$amount = function_exists( 'edd_get_payment_amount' ) ? (float) edd_get_payment_amount( $order_id ) : null;
		$this->emit(
			array(
				'type'      => EventType::REFUND,
				'entity_id' => (string) $order_id . '-r',
				'value'     => $amount,
				'currency'  => function_exists( 'edd_get_payment_currency_code' ) ? edd_get_payment_currency_code( $order_id ) : null,
				'meta'      => array( 'refund_id' => $refund_id ),
			)
		);
	}

	private function emit_purchase( int $payment_id ): void {
		$this->emit(
			array(
				'type'      => EventType::PURCHASE,
				'entity_id' => (string) $payment_id,
				'value'     => function_exists( 'edd_get_payment_amount' ) ? (float) edd_get_payment_amount( $payment_id ) : null,
				'currency'  => function_exists( 'edd_get_payment_currency_code' ) ? edd_get_payment_currency_code( $payment_id ) : null,
				'items'     => $this->payment_items( $payment_id ),
				'user_data' => $this->available_values(
					array(
						'email' => function_exists( 'edd_get_payment_user_email' ) ? edd_get_payment_user_email( $payment_id ) : '',
					)
				),
			)
		);
	}

	public function maybe_begin_checkout(): void {
		if ( ! function_exists( 'edd_is_checkout' ) || ! edd_is_checkout() ) {
			return;
		}

		$this->emit(
			array(
				'type'      => EventType::BEGIN_CHECKOUT,
				'entity_id' => '',
			)
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function payment_items( int $payment_id ): array {
		$details = function_exists( 'edd_get_payment_meta_cart_details' ) ? edd_get_payment_meta_cart_details( $payment_id ) : array();
		if ( ! is_array( $details ) ) {
			return array();
		}

		$items = array();
		foreach ( $details as $detail ) {
			if ( ! is_array( $detail ) ) {
				continue;
			}
			$items[] = array(
				'item_id'   => $detail['id'] ?? ( $detail['item_number']['id'] ?? '' ),
				'item_name' => $detail['name'] ?? '',
				'quantity'  => $detail['quantity'] ?? 1,
				'price'     => isset( $detail['price'] ) ? (float) $detail['price'] : null,
			);
		}
		return $items;
	}

	/**
	 * @param array<string,mixed> $values
	 * @return array<string,mixed>
	 */
	private function available_values( array $values ): array {
		return array_filter(
			$values,
			static function ( $value ): bool {
				return null !== $value && '' !== $value;
			}
		);
	}
}
