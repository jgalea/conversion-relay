<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\NormalizedEvent;
use WPConversionHub\Support\Settings;

// Generic webhook destination with SSRF protection.
final class Webhook extends AbstractDestination {

	public function id(): string {
		return 'webhook';
	}

	public function label(): string {
		return 'Webhook';
	}

	public function transports(): array {
		return array( self::TRANSPORT_SERVER );
	}

	public function capabilities(): array {
		return array_merge(
			parent::capabilities(),
			array(
				'items'   => true,
				'revenue' => true,
			)
		);
	}

	public function settings_fields(): array {
		return array(
			'url' => array(
				'label'   => __( 'Webhook URL', 'conversion-relay' ),
				'type'    => 'text',
				'secret'  => false,
				'default' => 'https://example.com/hook',
			),
		);
	}

	protected function required_keys(): array {
		return array( 'url' );
	}

	public function client_config(): array {
		return array();
	}

	public function send_server( NormalizedEvent $event ): DeliveryResult {
		$url = $this->get( 'url' );
		if ( ! $this->is_safe_url( $url ) ) {
			return DeliveryResult::failure( __( 'Blocked or invalid webhook URL.', 'conversion-relay' ) );
		}

		// Every other destination puts customer data through the enhanced-
		// conversions opt-in. This one used to post the raw event, which sent
		// plaintext email, phone and names to an arbitrary URL even with the
		// opt-in switched off, contradicting what the consent tab promises.
		$payload              = $event->to_array();
		$payload['user_data'] = $this->hashed_user_data( $event );
		unset( $payload['identity']['user_agent'] );
		if ( ! Settings::enhanced_conversions_enabled() ) {
			$payload['identity'] = array_intersect_key( $payload['identity'], array_flip( array( 'client_id', 'session_id', 'session_number' ) ) );
		}

		return $this->post_json( $url, $payload );
	}

	private function is_safe_url( string $url ): bool {
		// https only: the payload carries customer data, so plaintext transport
		// is not an option the admin should be able to pick by accident.
		// wp_http_validate_url rejects private and reserved ranges, and
		// wp_safe_remote_post checks again when the request is made.
		return 0 === strpos( $url, 'https://' ) && false !== wp_http_validate_url( $url );
	}
}
