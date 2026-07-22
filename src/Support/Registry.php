<?php

declare( strict_types=1 );

namespace WPConversionHub\Support;

use WPConversionHub\Destinations\DestinationInterface;
use WPConversionHub\Sources\SourceInterface;

final class Registry {

	/** @var array<string,SourceInterface> */
	private array $sources = array();

	/** @var array<string,DestinationInterface> */
	private array $destinations = array();

	public function add_source( SourceInterface $source ): void {
		$this->sources[ $source->id() ] = $source;
	}

	public function add_destination( DestinationInterface $destination ): void {
		$this->destinations[ $destination->id() ] = $destination;
	}

	/**
	 * @return array<string,SourceInterface>
	 */
	public function sources(): array {
		return $this->sources;
	}

	/**
	 * @return array<string,DestinationInterface>
	 */
	public function destinations(): array {
		return $this->destinations;
	}

	public function source( string $id ): ?SourceInterface {
		return $this->sources[ $id ] ?? null;
	}

	public function destination( string $id ): ?DestinationInterface {
		return $this->destinations[ $id ] ?? null;
	}
}
