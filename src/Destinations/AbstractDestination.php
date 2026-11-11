<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\NormalizedEvent;
use WPConversionHub\Support\Hashing;
use WPConversionHub\Support\Settings;

abstract class AbstractDestination implements DestinationInterface {

	public function consent_category(): string {
		return self::CONSENT_ANALYTICS;
	}

	public function capabilities(): array {
		return array(
			'server'    => in_array( self::TRANSPORT_SERVER, $this->transports(), true ),
			'client'    => in_array( self::TRANSPORT_CLIENT, $this->transports(), true ),
			'items'     => false,
			'revenue'   => false,
			'user_data' => false,
			'dedup'     => false,
		);
	}

	public function client_config(): array {
		return array();
	}

	public function send_server( NormalizedEvent $event ): DeliveryResult {
		return DeliveryResult::failure( __( 'Server transport not implemented.', 'conversion-relay' ) );
	}

	/**
	 * Event types a platform needs its own conversion id or label for.
	 *
	 * @return array<string,string> type => label
	 */
	protected static function conversion_types(): array {
		return array(
			'form_submission' => __( 'Form submission', 'conversion-relay' ),
			'add_to_cart'     => __( 'Add to cart', 'conversion-relay' ),
			'begin_checkout'  => __( 'Begin checkout', 'conversion-relay' ),
			'register'        => __( 'Registration', 'conversion-relay' ),
			'subscribe'       => __( 'Subscription', 'conversion-relay' ),
			'donation'        => __( 'Donation', 'conversion-relay' ),
		);
	}

	/**
	 * Saved values for the per-event-type fields named "<prefix><type>".
	 *
	 * @return array<string,string> type => value, empty values left out
	 */
	protected function per_type_values( string $prefix ): array {
		$values = array();
		foreach ( array_keys( self::conversion_types() ) as $type ) {
			$value = $this->get( $prefix . $type );
			if ( '' !== $value ) {
				$values[ $type ] = $value;
			}
		}
		return $values;
	}

	protected function get( string $key, string $default = '' ): string {
		$cfg = Settings::dest_config( $this->id() );
		$val = $cfg[ $key ] ?? $default;
		return is_scalar( $val ) ? (string) $val : $default;
	}

	/**
	 * Required config keys that must be non-empty for this destination to be usable.
	 *
	 * @return string[]
	 */
	protected function required_keys(): array {
		return array();
	}

	public function is_configured(): bool {
		foreach ( $this->required_keys() as $key ) {
			if ( '' === $this->get( $key ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return array<string,string>
	 */
	protected function hashed_user_data( NormalizedEvent $event ): array {
		if ( ! Settings::enhanced_conversions_enabled() || empty( $event->user_data ) ) {
			return array();
		}
		return Hashing::user_data( $event->user_data );
	}

	/**
	 * Non-blocking-friendly POST used by server transports.
	 *
	 * @param array<string,mixed> $body
	 */
	protected function post_json( string $url, array $body, array $headers = array() ): DeliveryResult {
		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 0,
				'headers'     => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
				'body'        => (string) wp_json_encode( $body ),
				'blocking'    => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return DeliveryResult::failure( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return DeliveryResult::success( (string) wp_remote_retrieve_body( $response ), $code );
		}

		return DeliveryResult::failure(
			sprintf(
				/* translators: 1: HTTP status code, 2: response body from the destination. */
				__( 'HTTP %1$d: %2$s', 'conversion-relay' ),
				$code,
				wp_remote_retrieve_body( $response )
			),
			$code
		);
	}
}
