<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\NormalizedEvent;
use WPConversionHub\Hub\Consent;
use WPConversionHub\Hub\Dispatcher;
use WPConversionHub\Hub\RateLimit;
use WPConversionHub\Support\Identity;

abstract class AbstractSource implements SourceInterface {

	protected ?Dispatcher $hub = null;

	public function register( Dispatcher $hub ): void {
		$this->hub = $hub;
		$this->hooks();
	}

	/**
	 * Attach WordPress hooks. Runs only when the source is enabled and available.
	 */
	abstract protected function hooks(): void;

	public function client_selectors(): array {
		return array();
	}

	/**
	 * Emit an event any anonymous visitor can trigger, at most once per key per
	 * hour and inside the shared rate budget.
	 *
	 * @param array<string,mixed> $args
	 */
	protected function emit_public( array $args, string $dedupe_key ): void {
		$transient = 'wpch_once_' . md5( $dedupe_key );
		if ( false !== get_transient( $transient ) ) {
			return;
		}
		set_transient( $transient, 1, HOUR_IN_SECONDS );

		if ( RateLimit::ok() ) {
			$this->emit( $args );
		}
	}

	/**
	 * Identity and consent as they are in the visitor's own request, to be stored
	 * on an order so a later delivery doesn't read the request that triggers it.
	 */
	protected function visitor_snapshot(): string {
		return (string) wp_json_encode(
			array(
				'identity' => Identity::capture(),
				'consent'  => Consent::snapshot(),
			)
		);
	}

	/**
	 * @param mixed $stored A snapshot written by visitor_snapshot(), or nothing.
	 * @return array<string,mixed> Event args: identity (empty when unknown) and consent (null when unknown).
	 */
	protected function stored_visitor( $stored ): array {
		$data = is_string( $stored ) ? json_decode( $stored, true ) : null;
		$data = is_array( $data ) ? $data : array();

		return array(
			'identity' => is_array( $data['identity'] ?? null ) ? $data['identity'] : array(),
			'consent'  => is_array( $data['consent'] ?? null ) ? $data['consent'] : null,
		);
	}

	/**
	 * Build a normalized event scoped to this source and dispatch it.
	 *
	 * @param array<string,mixed> $args
	 */
	protected function emit( array $args ): void {
		if ( null === $this->hub ) {
			return;
		}
		$args['source'] = $this->id();
		if ( ! isset( $args['url'] ) ) {
			$args['url'] = home_url( add_query_arg( array() ) );
		}
		$this->hub->dispatch( NormalizedEvent::create( $args ) );
	}
}
