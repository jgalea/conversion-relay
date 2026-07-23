<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\NormalizedEvent;

// Matomo server-side tracking via the HTTP Tracking API.
final class Matomo extends AbstractDestination {

	public function id(): string {
		return 'matomo';
	}

	public function label(): string {
		return 'Matomo';
	}

	public function transports(): array {
		return array( self::TRANSPORT_SERVER );
	}

	public function capabilities(): array {
		return array_merge(
			parent::capabilities(),
			array( 'revenue' => true )
		);
	}

	public function settings_fields(): array {
		return array(
			'url'        => array(
				'label'   => 'Matomo URL',
				'type'    => 'text',
				'secret'  => false,
				'default' => 'https://analytics.example.com/',
			),
			'site_id'    => array(
				'label'  => 'Site ID',
				'type'   => 'text',
				'secret' => false,
			),
			'token_auth' => array(
				'label'  => 'Auth token',
				'type'   => 'text',
				'secret' => true,
			),
		);
	}

	protected function required_keys(): array {
		return array( 'url', 'site_id' );
	}

	public function client_config(): array {
		return array();
	}

	public function send_server( NormalizedEvent $event ): DeliveryResult {
		$base     = rtrim( $this->get( 'url' ), '/' );
		$endpoint = $base . '/matomo.php';

		$params = array(
			'idsite' => $this->get( 'site_id' ),
			'rec'    => 1,
			'apiv'   => 1,
			'url'    => $event->url ?: home_url( '/' ),
			'e_c'    => 'conversion',
			'e_a'    => $event->type,
			'e_n'    => $event->entity_id,
			'cid'    => substr( md5( ( $event->identity['client_id'] ?? '' ) . $event->event_id ), 0, 16 ),
			'rand'   => substr( md5( $event->event_id ), 0, 8 ),
		);

		if ( null !== $event->value ) {
			$params['e_v'] = $event->value;
		}

		$token = $this->get( 'token_auth' );
		if ( '' !== $token ) {
			$params['token_auth'] = $token;
			if ( ! empty( $event->identity['ip'] ) ) {
				$params['cip'] = $event->identity['ip'];
			}
		}

		$response = wp_safe_remote_get(
			add_query_arg( $params, $endpoint ),
			array(
				'timeout'     => 5,
				'redirection' => 0,
			)
		);

		if ( is_wp_error( $response ) ) {
			return DeliveryResult::failure( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return DeliveryResult::success( '', $code );
		}

		return DeliveryResult::failure( 'HTTP ' . $code, $code );
	}
}
