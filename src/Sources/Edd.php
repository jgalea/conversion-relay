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
		return array( EventType::PURCHASE, EventType::BEGIN_CHECKOUT );
	}

	protected function hooks(): void {
		add_action( 'edd_complete_purchase', array( $this, 'on_complete' ) );
		add_action( 'template_redirect', array( $this, 'maybe_begin_checkout' ) );
	}

	public function on_complete( int $payment_id ): void {
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
