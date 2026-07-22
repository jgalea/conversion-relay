<?php

declare( strict_types=1 );

namespace WPConversionHub\Event;

/**
 * Canonical event every source emits and every destination consumes.
 */
final class NormalizedEvent {

	public const SCHEMA_VERSION = 1;

	public string $type;
	public string $source;
	public string $event_id;
	public string $entity_id;
	public ?float $value;
	public ?string $currency;
	public string $origin;
	public int $timestamp;
	public string $url;

	/** @var array<int,array<string,mixed>> */
	public array $items;

	/** @var array<string,mixed> Raw PII, hashed by destinations at send time. */
	public array $user_data;

	/** @var array<string,mixed> client_id, fbp, fbc, gclid, wbraid, gbraid, session id. */
	public array $identity;

	/** @var array<string,mixed> */
	public array $meta;

	/**
	 * @param array<string,mixed> $args
	 */
	private function __construct( array $args ) {
		$this->type      = (string) ( $args['type'] ?? '' );
		$this->source    = (string) ( $args['source'] ?? '' );
		$this->entity_id = (string) ( $args['entity_id'] ?? '' );
		$this->event_id  = (string) ( $args['event_id'] ?? '' ) ?: self::mint_id( $this->source, $this->type, $this->entity_id );
		$this->value     = isset( $args['value'] ) ? (float) $args['value'] : null;
		$this->currency  = isset( $args['currency'] ) ? (string) $args['currency'] : null;
		$this->origin    = (string) ( $args['origin'] ?? 'server' );
		$this->timestamp = (int) ( $args['timestamp'] ?? time() );
		$this->url       = (string) ( $args['url'] ?? '' );
		$this->items     = is_array( $args['items'] ?? null ) ? $args['items'] : array();
		$this->user_data = is_array( $args['user_data'] ?? null ) ? $args['user_data'] : array();
		$this->identity  = is_array( $args['identity'] ?? null ) ? $args['identity'] : array();
		$this->meta      = is_array( $args['meta'] ?? null ) ? $args['meta'] : array();
	}

	/**
	 * @param array<string,mixed> $args
	 */
	public static function create( array $args ): self {
		return new self( $args );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function from_array( array $data ): self {
		return new self( $data );
	}

	/**
	 * Deterministic-when-possible id, so redirects and retries do not mint a new one.
	 */
	public static function mint_id( string $source, string $type, string $entity_id ): string {
		if ( '' !== $entity_id ) {
			return substr( md5( $source . '|' . $type . '|' . $entity_id ), 0, 24 );
		}
		return substr( md5( uniqid( $source . $type, true ) ), 0, 24 );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'type'           => $this->type,
			'source'         => $this->source,
			'event_id'       => $this->event_id,
			'entity_id'      => $this->entity_id,
			'value'          => $this->value,
			'currency'       => $this->currency,
			'origin'         => $this->origin,
			'timestamp'      => $this->timestamp,
			'url'            => $this->url,
			'items'          => $this->items,
			'user_data'      => $this->user_data,
			'identity'       => $this->identity,
			'meta'           => $this->meta,
		);
	}

	public function is_valid(): bool {
		return '' !== $this->type && EventType::is_valid( $this->type ) && '' !== $this->source;
	}
}
