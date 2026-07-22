<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\NormalizedEvent;

/** Umami Analytics client + server send API. */
final class Umami extends AbstractDestination {

	public function id(): string {
		return 'umami';
	}

	public function label(): string {
		return 'Umami';
	}

	public function transports(): array {
		$transports = array( self::TRANSPORT_CLIENT );
		if ( '' !== $this->get( 'website_id' ) ) {
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
			'website_id' => array(
				'label'  => __( 'Website ID', 'wp-conversion-hub' ),
				'type'   => 'text',
				'secret' => false,
			),
			'host'       => array(
				'label'   => __( 'Host (https://cloud.umami.is or self-hosted)', 'wp-conversion-hub' ),
				'type'    => 'text',
				'secret'  => false,
				'default' => 'https://cloud.umami.is',
			),
		);
	}

	protected function required_keys(): array {
		return array( 'website_id' );
	}

	public function client_config(): array {
		return array(
			'website_id' => $this->get( 'website_id' ),
			'host'       => $this->get( 'host' ),
		);
	}

	public function send_server( NormalizedEvent $event ): DeliveryResult {
		$host = rtrim( $this->get( 'host', 'https://cloud.umami.is' ), '/' );
		$url  = $host . '/api/send';

		$body = array(
			'type'    => 'event',
			'payload' => array(
				'website'  => $this->get( 'website_id' ),
				'hostname' => wp_parse_url( home_url(), PHP_URL_HOST ),
				'url'      => $event->url,
				'name'     => $event->type,
				'data'     => array_filter(
					array(
						'value'    => $event->value,
						'currency' => $event->currency,
						'event_id' => $event->event_id,
					),
					static function ( $v ) {
						return null !== $v;
					}
				),
			),
		);

		$headers = array(
			'User-Agent' => (string) ( $event->identity['user_agent'] ?? 'WP Conversion Hub' ),
		);

		return $this->post_json( $url, $body, $headers );
	}
}
