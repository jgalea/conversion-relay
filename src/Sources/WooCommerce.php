<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits WooCommerce conversion events from CRUD-safe order and cart hooks.
final class WooCommerce extends AbstractSource {

	public function id(): string {
		return 'woocommerce';
	}

	public function label(): string {
		return 'WooCommerce';
	}

	public function is_available(): bool {
		return class_exists( 'WooCommerce' );
	}

	public function supported_events(): array {
		return array( EventType::PURCHASE, EventType::ADD_TO_CART, EventType::BEGIN_CHECKOUT, EventType::REFUND );
	}

	protected function hooks(): void {
		add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_paid' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'on_add_to_cart' ), 10, 6 );
		add_action( 'template_redirect', array( $this, 'maybe_begin_checkout' ) );
		add_action( 'woocommerce_order_refunded', array( $this, 'on_refund' ), 10, 2 );
	}

	public function on_payment_complete( int $order_id ): void {
		$this->emit_purchase( $order_id );
	}

	public function on_paid( int $order_id ): void {
		$this->emit_purchase( $order_id );
	}

	/**
	 * @param mixed $variation
	 * @param mixed $cart_item_data
	 */
	public function on_add_to_cart( string $cart_item_key, int $product_id, int $quantity, int $variation_id, $variation, $cart_item_data ): void {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $variation_id ?: $product_id ) : null;
		$price   = is_object( $product ) && method_exists( $product, 'get_price' ) ? (float) $product->get_price() : null;
		$name    = is_object( $product ) && method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '';

		$this->emit(
			array(
				'type'      => EventType::ADD_TO_CART,
				'entity_id' => (string) $product_id,
				'value'     => null === $price ? null : $price * $quantity,
				'currency'  => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : null,
				'items'     => array(
					array(
						'item_id'   => $variation_id ?: $product_id,
						'item_name' => $name,
						'quantity'  => $quantity,
						'price'     => $price,
					),
				),
			)
		);
	}

	public function maybe_begin_checkout(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return;
		}

		$cart  = function_exists( 'WC' ) && WC() ? WC()->cart : null;
		$value = $cart ? (float) $cart->get_total( 'edit' ) : null;

		$this->emit(
			array(
				'type'      => EventType::BEGIN_CHECKOUT,
				'entity_id' => '',
				'value'     => $value,
				'currency'  => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : null,
			)
		);
	}

	public function on_refund( int $order_id, int $refund_id ): void {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return;
		}

		$this->emit(
			array(
				'type'      => EventType::REFUND,
				'entity_id' => (string) $order_id . '-r',
				'value'     => (float) $order->get_total_refunded(),
				'currency'  => $order->get_currency(),
				'meta'      => array( 'refund_id' => $refund_id ),
			)
		);
	}

	private function emit_purchase( int $order_id ): void {
		static $seen = array();

		if ( isset( $seen[ $order_id ] ) ) {
			return;
		}
		$seen[ $order_id ] = true;

		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return;
		}

		$this->emit(
			array(
				'type'      => EventType::PURCHASE,
				'entity_id' => (string) $order->get_id(),
				'value'     => (float) $order->get_total(),
				'currency'  => $order->get_currency(),
				'items'     => $this->order_items( $order ),
				'user_data' => $this->available_values(
					array(
						'email'      => $order->get_billing_email(),
						'phone'      => $order->get_billing_phone(),
						'first_name' => $order->get_billing_first_name(),
						'last_name'  => $order->get_billing_last_name(),
					)
				),
			)
		);
	}

	/**
	 * @param object $order
	 * @return array<int,array<string,mixed>>
	 */
	private function order_items( $order ): array {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}
			$quantity = method_exists( $item, 'get_quantity' ) ? (float) $item->get_quantity() : 0.0;
			$total    = method_exists( $item, 'get_total' ) ? (float) $item->get_total() : 0.0;
			$items[]  = array(
				'item_id'   => method_exists( $item, 'get_product_id' ) ? $item->get_product_id() : '',
				'item_name' => method_exists( $item, 'get_name' ) ? $item->get_name() : '',
				'quantity'  => $quantity,
				'price'     => $quantity > 0 ? $total / $quantity : $total,
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
