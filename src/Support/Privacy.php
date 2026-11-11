<?php

declare( strict_types=1 );

namespace WPConversionHub\Support;

/**
 * Takes the identity and consent saved on orders (IP, user agent, click ids) into
 * the site's Export and Erase Personal Data tools, found by billing email.
 */
final class Privacy {

	public const META_KEY = '_wpch_visitor';

	private const PER_PAGE = 50;

	public static function hooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
	}

	/**
	 * @param array<string,mixed> $exporters
	 * @return array<string,mixed>
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters['conversion-relay'] = array(
			'exporter_friendly_name' => __( 'Conversion Relay order tracking data', 'conversion-relay' ),
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	/**
	 * @param array<string,mixed> $erasers
	 * @return array<string,mixed>
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['conversion-relay'] = array(
			'eraser_friendly_name' => __( 'Conversion Relay order tracking data', 'conversion-relay' ),
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * @return array{data:array<int,array<string,mixed>>,done:bool}
	 */
	public static function export( string $email, int $page = 1 ): array {
		$data   = array();
		$orders = self::orders( $email, $page );

		foreach ( $orders['rows'] as $row ) {
			$stored = json_decode( (string) $row['value'], true );
			if ( ! is_array( $stored ) || empty( $stored['identity'] ) || ! is_array( $stored['identity'] ) ) {
				continue;
			}
			$fields = array();
			foreach ( $stored['identity'] as $name => $value ) {
				$fields[] = array(
					'name'  => (string) $name,
					'value' => (string) $value,
				);
			}
			$data[] = array(
				'group_id'    => 'conversion-relay',
				'group_label' => __( 'Conversion tracking', 'conversion-relay' ),
				'item_id'     => 'conversion-relay-order-' . $row['id'],
				'data'        => $fields,
			);
		}

		return array(
			'data' => $data,
			'done' => $orders['done'],
		);
	}

	/**
	 * @return array{items_removed:bool,items_retained:bool,messages:array<int,string>,done:bool}
	 */
	public static function erase( string $email, int $page = 1 ): array {
		$removed = false;
		$orders  = self::orders( $email, $page );

		foreach ( $orders['rows'] as $row ) {
			if ( '' === (string) $row['value'] ) {
				continue;
			}
			( $row['delete'] )();
			$removed = true;
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => $orders['done'],
		);
	}

	/**
	 * One page of WooCommerce and Easy Digital Downloads orders for this billing
	 * email, each with its saved snapshot and a closure that removes it.
	 *
	 * @return array{rows:array<int,array{id:int,value:mixed,delete:callable}>,done:bool}
	 */
	private static function orders( string $email, int $page ): array {
		$rows = array();
		$full = false;

		if ( function_exists( 'wc_get_orders' ) ) {
			$found = wc_get_orders(
				array(
					'billing_email' => $email,
					'limit'         => self::PER_PAGE,
					'paged'         => $page,
					'return'        => 'objects',
				)
			);
			$full  = count( $found ) >= self::PER_PAGE;
			foreach ( $found as $order ) {
				$rows[] = array(
					'id'     => (int) $order->get_id(),
					'value'  => $order->get_meta( self::META_KEY ),
					'delete' => static function () use ( $order ): void {
						$order->delete_meta_data( self::META_KEY );
						$order->save_meta_data();
					},
				);
			}
		}

		if ( function_exists( 'edd_get_orders' ) && function_exists( 'edd_get_order_meta' ) && function_exists( 'edd_delete_order_meta' ) ) {
			$found = edd_get_orders(
				array(
					'email'  => $email,
					'number' => self::PER_PAGE,
					'offset' => ( $page - 1 ) * self::PER_PAGE,
				)
			);
			$full  = $full || count( $found ) >= self::PER_PAGE;
			foreach ( $found as $order ) {
				$id     = (int) $order->id;
				$rows[] = array(
					'id'     => $id,
					'value'  => edd_get_order_meta( $id, self::META_KEY, true ),
					'delete' => static function () use ( $id ): void {
						edd_delete_order_meta( $id, self::META_KEY );
					},
				);
			}
		}

		return array(
			'rows' => $rows,
			'done' => ! $full,
		);
	}
}
