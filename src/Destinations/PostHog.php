<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

use WPConversionHub\Event\NormalizedEvent;

// Sends client and server capture events to PostHog.
final class PostHog extends AbstractDestination {

	public function id(): string {
		return 'posthog';
	}

	public function label(): string {
		return 'PostHog';
	}

	public function transports(): array {
		$transports = array( self::TRANSPORT_CLIENT );
		if ( '' !== $this->get( 'api_key' ) ) {
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
			'api_key' => array(
				'label'  => __( 'Project API key', 'conversion-relay' ),
				'type'   => 'text',
				'secret' => false,
			),
			'host'    => array(
				'label'   => __( 'Host', 'conversion-relay' ),
				'type'    => 'text',
				'default' => 'https://us.i.posthog.com',
				'secret'  => false,
			),
		);
	}

	protected function required_keys(): array {
		return array( 'api_key' );
	}

	public function client_config(): array {
		return array(
			'api_key' => $this->get( 'api_key' ),
			'host'    => $this->get( 'host', 'https://us.i.posthog.com' ),
		);
	}

	public function send_server( NormalizedEvent $event ): DeliveryResult {
		$api_key = $this->get( 'api_key' );
		if ( '' === $api_key ) {
			return DeliveryResult::failure( 'No PostHog API key configured.' );
		}

		$host = rtrim( $this->get( 'host', 'https://us.i.posthog.com' ), '/' );
		$body = array(
			'api_key'     => $api_key,
			'event'       => $event->type,
			'distinct_id' => ( (string) ( $event->identity['client_id'] ?? '' ) ) ?: 'wpch-' . $event->event_id,
			'properties'  => array_filter(
				array(
					'value'        => $event->value,
					'currency'     => $event->currency,
					'event_id'     => $event->event_id,
					'$current_url' => $event->url,
				),
				static function ( $value ): bool {
					return null !== $value;
				}
			),
		);

		return $this->post_json( $host . '/capture/', $body );
	}
}
