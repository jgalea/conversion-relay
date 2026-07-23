<?php

declare( strict_types=1 );

namespace WPConversionHub\Hub;

use WPConversionHub\Destinations\DestinationInterface;
use WPConversionHub\Event\NormalizedEvent;
use WPConversionHub\Storage\EventLog;
use WPConversionHub\Support\Identity;
use WPConversionHub\Support\License;
use WPConversionHub\Support\Registry;
use WPConversionHub\Support\Settings;

/**
 * The hub. Receives normalized events from sources and the JS bridge, applies
 * consent + dedup, then routes each event to enabled destinations: server
 * transports go through the durable queue, client transports through the
 * redirect-safe client buffer.
 */
final class Dispatcher {

	private Registry $registry;

	public function __construct( Registry $registry ) {
		$this->registry = $registry;
	}

	public function dispatch( NormalizedEvent $event ): void {
		if ( ! $event->is_valid() ) {
			return;
		}

		if ( empty( $event->identity ) ) {
			$event->identity = Identity::capture();
		}

		$event = apply_filters( 'wpch_event', $event );
		if ( ! $event instanceof NormalizedEvent ) {
			return;
		}

		$channel      = Settings::source_channel( $event->source );
		$client_dests = array();

		foreach ( $this->registry->destinations() as $destination ) {
			if ( ! Settings::dest_enabled( $destination->id() ) || ! $destination->is_configured() ) {
				continue;
			}

			if ( DestinationInterface::TIER_PRO === $destination->tier() && ! License::is_pro() ) {
				continue;
			}

			if ( ! Consent::allows( $destination, $event ) ) {
				continue;
			}

			$transports     = $destination->transports();
			$server_capable = in_array( DestinationInterface::TRANSPORT_SERVER, $transports, true );
			$client_capable = in_array( DestinationInterface::TRANSPORT_CLIENT, $transports, true );

			if ( $server_capable && 'client_only' !== $channel ) {
				$this->route_server( $event, $destination );
				continue;
			}

			if ( $client_capable && 'server_only' !== $channel ) {
				$client_dests[] = $destination->id();
			}
		}

		if ( ! empty( $client_dests ) ) {
			ClientQueue::add( $this->client_event( $event ), $client_dests );
		}
	}

	private function route_server( NormalizedEvent $event, DestinationInterface $destination ): void {
		$recorded = EventLog::record_pending(
			$event->event_id,
			$destination->id(),
			$event->type,
			$event->source,
			$event->entity_id,
			$event->to_array()
		);

		if ( $recorded ) {
			Queue::enqueue( $event->to_array(), $destination->id() );
		}
	}

	/**
	 * The worker: deliver one event to one destination, with retry + dead-letter.
	 *
	 * @param array<string,mixed> $args
	 */
	public function deliver( array $args ): void {
		$event_data  = isset( $args['event'] ) && is_array( $args['event'] ) ? $args['event'] : array();
		$destination = (string) ( $args['destination'] ?? '' );

		$dest = $this->registry->destination( $destination );
		if ( null === $dest ) {
			return;
		}

		$event = NormalizedEvent::from_array( $event_data );

		if ( EventLog::already_sent( $event->event_id, $destination ) ) {
			return;
		}

		try {
			$result = $dest->send_server( $event );
		} catch ( \Throwable $e ) {
			$result = \WPConversionHub\Destinations\DeliveryResult::failure( $e->getMessage() );
		}

		if ( $result->ok() ) {
			EventLog::mark_sent( $event->event_id, $destination, $result->message() );
			return;
		}

		$attempts = $this->attempt_count( $event->event_id, $destination ) + 1;
		EventLog::mark_failed( $event->event_id, $destination, $result->message(), $attempts );

		if ( $attempts < EventLog::MAX_ATTEMPTS ) {
			Queue::enqueue( $event->to_array(), $destination, Queue::backoff_delay( $attempts ) );
		}
	}

	private function attempt_count( string $event_id, string $destination ): int {
		foreach ( EventLog::recent( 200 ) as $row ) {
			if ( ( $row['event_id'] ?? '' ) === $event_id && ( $row['destination'] ?? '' ) === $destination ) {
				return (int) ( $row['attempts'] ?? 0 );
			}
		}
		return 0;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function client_event( NormalizedEvent $event ): array {
		return array(
			'event_id' => $event->event_id,
			'type'     => $event->type,
			'value'    => $event->value,
			'currency' => $event->currency,
			'items'    => $event->items,
			'url'      => $event->url,
		);
	}

	public function registry(): Registry {
		return $this->registry;
	}
}
