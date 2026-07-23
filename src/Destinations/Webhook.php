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
				'label'   => 'Webhook URL',
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

		return $this->post_json( $url, $event->to_array() );
	}

	private function is_safe_url( string $url ): bool {
		$p = wp_parse_url( $url );
		if ( empty( $p['host'] ) || ! isset( $p['scheme'] ) || ! in_array( $p['scheme'], array( 'http', 'https' ), true ) ) {
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
