<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Solid Affiliate new affiliate registrations.
final class SolidAffiliate extends AbstractSource {

	public function id(): string {
		return 'solidaffiliate';
	}

	public function label(): string {
		return 'Solid Affiliate';
	}

	public function is_available(): bool {
		return defined( 'SOLID_AFFILIATE_VERSION' ) || class_exists( 'SolidAffiliate\\Loader' );
	}

	public function supported_events(): array {
		return array( EventType::REGISTER );
	}

	protected function hooks(): void {
		add_action( 'solid_affiliate/Affiliate/new_registration/success', array( $this, 'on_registered' ) );
	}

	/**
	 * @param mixed $affiliate Solid Affiliate affiliate object when the plugin is loaded.
	 */
	public function on_registered( $affiliate ): void {
		if ( ! is_object( $affiliate ) ) {
			return;
		}

		$this->emit(
			array(
				'type'      => EventType::REGISTER,
				'entity_id' => isset( $affiliate->id ) ? (string) $affiliate->id : '',
			)
		);
	}
}
