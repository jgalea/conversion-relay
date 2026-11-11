<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Easy Digital Downloads purchase and checkout events.
final class Edd extends AbstractSource {

	private const VISITOR_META = '_wpch_visitor';

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
		add_action( 'edd_built_order', array( $this, 'store_visitor' ) );
		add_action( 'edd_insert_payment', array( $this, 'store_visitor' ) );
		add_action( 'edd_complete_purchase', array( $this, 'on_complete' ) );
		// Catches recurring renewals, which reach a paid status without firing
		// edd_complete_purchase. The deterministic event_id dedupes any overlap
		// with the initial purchase above.
		add_action( 'edd_update_payment_status', array( $this, 'on_status' ), 10, 3 );
		add_action( 'template_redirect', array( $this, 'maybe_begin_checkout' ) );
		add_action( 'edd_refund_order', array( $this, 'on_refund' ), 10, 2 );
	}

	/**
	 * Keep the buyer's identity and consent with the order, so a payment
	 * completed by a gateway webhook or an admin isn't attributed to them.
	 */
	public function store_visitor( int $order_id ): void {
		if ( function_exists( 'edd_update_order_meta' ) ) {
			edd_update_order_meta( $order_id, self::VISITOR_META, $this->visitor_snapshot() );
		} else {
			update_post_meta( $order_id, self::VISITOR_META, $this->visitor_snapshot() );
		}
	}

	private function stored( int $order_id ): array {
		$raw = function_exists( 'edd_get_order_meta' )
			? edd_get_order_meta( $order_id, self::VISITOR_META, true )
			: get_post_meta( $order_id, self::VISITOR_META, true );

		return $this->stored_visitor( $raw );
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
		$refund = function_exists( 'edd_get_order' ) && $refund_id ? edd_get_order( $refund_id ) : null;
		$amount = is_object( $refund ) && isset( $refund->total )
			? abs( (float) $refund->total )
			: ( function_exists( 'edd_get_payment_amount' ) ? (float) edd_get_payment_amount( $order_id ) : null );

		$this->emit(
			array_merge(
				array(
					'type'      => EventType::REFUND,
					'entity_id' => (string) ( $refund_id ?: $order_id . '-r' ),
					'value'     => $amount,
					'currency'  => function_exists( 'edd_get_payment_currency_code' ) ? edd_get_payment_currency_code( $order_id ) : null,
					'meta'      => array(
						'refund_id' => $refund_id,
						'order_id'  => $order_id,
					),
				),
				$this->stored( $order_id )
			)
		);
	}

	private function emit_purchase( int $payment_id ): void {
		$this->emit(
			array_merge(
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
				),
				$this->stored( $payment_id )
			)
		);
	}

	public function maybe_begin_checkout(): void {
		if ( ! function_exists( 'edd_is_checkout' ) || ! edd_is_checkout() ) {
			return;
		}

		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = wp_hash( (string) wp_json_encode( function_exists( 'edd_get_cart_contents' ) ? edd_get_cart_contents() : array() ) . '|' . $ip );

		$this->emit_public(
			array(
				'type'      => EventType::BEGIN_CHECKOUT,
				'entity_id' => $key,
			),
			$key
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
