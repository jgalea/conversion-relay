<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\NormalizedEvent;

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
			return DeliveryResult::failure( 'Blocked or invalid webhook URL.' );
		}

		// Every other destination puts customer data through the enhanced-
		// conversions opt-in. This one used to post the raw event, which sent
		// plaintext email, phone and names to an arbitrary URL even with the
		// opt-in switched off, contradicting what the consent tab promises.
		$payload              = $event->to_array();
		$payload['user_data'] = $this->hashed_user_data( $event );
		unset( $payload['identity']['user_agent'] );

		return $this->post_json( $url, $payload );
	}

	private function is_safe_url( string $url ): bool {
		$p = wp_parse_url( $url );
		// https only: the payload carries customer data, so plaintext transport
		// is not an option the admin should be able to pick by accident.
		if ( empty( $p['host'] ) || ! isset( $p['scheme'] ) || 'https' !== $p['scheme'] ) {
			return false;
		}

		$host = $p['host'];
		if ( 'localhost' === strtolower( $host ) ) {
			return false;
		}

		$ip = filter_var( $host, FILTER_VALIDATE_IP ) ? $host : gethostbyname( $host );
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}

		return true;
	}
}
