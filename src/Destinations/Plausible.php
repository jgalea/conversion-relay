<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\NormalizedEvent;

/** Plausible Analytics client + server events API. */
final class Plausible extends AbstractDestination {

	public function id(): string {
		return 'plausible';
	}

	public function label(): string {
		return 'Plausible Analytics';
	}

	public function transports(): array {
		$transports = array( self::TRANSPORT_CLIENT );
		if ( '' !== $this->get( 'domain' ) ) {
			$transports[] = self::TRANSPORT_SERVER;
		}
		return $transports;
	}

	public function capabilities(): array {
		return array_merge(
			parent::capabilities(),
			array( 'revenue' => true )
		);
	}

	public function settings_fields(): array {
		return array(
			'domain'   => array(
				'label'  => __( 'Domain (example.com)', 'wp-conversion-hub' ),
				'type'   => 'text',
				'secret' => false,
			),
			'api_host' => array(
				'label'   => __( 'API host', 'wp-conversion-hub' ),
				'type'    => 'text',
				'secret'  => false,
				'default' => 'https://plausible.io',
			),
		);
	}

	protected function required_keys(): array {
		return array( 'domain' );
	}

	public function client_config(): array {
		return array( 'domain' => $this->get( 'domain' ) );
	}

	public function send_server( NormalizedEvent $event ): DeliveryResult {
		$api_host = $this->get( 'api_host', 'https://plausible.io' );
		$url      = rtrim( $api_host, '/' ) . '/api/event';

		$body = array(
			'name'   => $event->type,
			'url'    => $event->url ?: home_url( '/' ),
			'domain' => $this->get( 'domain' ),
			'props'  => array( 'event_id' => $event->event_id ),
		);

		if ( null !== $event->value ) {
			$body['revenue'] = array(
				'currency' => $event->currency ?: 'USD',
				'amount'   => $event->value,
			);
		}

		$headers = array(
			'User-Agent'      => (string) ( $event->identity['user_agent'] ?? 'WP Conversion Hub' ),
			'X-Forwarded-For' => (string) ( $event->identity['ip'] ?? '127.0.0.1' ),
		);

		return $this->post_json( $url, $body, $headers );
	}
}
