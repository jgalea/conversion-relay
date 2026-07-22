<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\NormalizedEvent;
use WPConversionHub\Hub\Dispatcher;

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
