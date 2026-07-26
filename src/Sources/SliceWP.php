<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits SliceWP affiliate registrations.
final class SliceWP extends AbstractSource {

	public function id(): string {
		return 'slicewp';
	}

	public function label(): string {
		return 'SliceWP';
	}

	public function is_available(): bool {
		return function_exists( 'slicewp' ) || defined( 'SLICEWP_VERSION' );
	}

	public function supported_events(): array {
		return array( EventType::REGISTER );
	}

	protected function hooks(): void {
		add_action( 'slicewp_register_affiliate', array( $this, 'on_registered' ) );
	}

	/**
	 * @param mixed $affiliate_id
	 */
	public function on_registered( $affiliate_id ): void {
		$this->emit(
			array(
				'type'      => EventType::REGISTER,
				'entity_id' => (string) $affiliate_id,
			)
		);
	}
}
