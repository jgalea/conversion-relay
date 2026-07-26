<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits AffiliateWP affiliate registrations.
final class AffiliateWP extends AbstractSource {

	public function id(): string {
		return 'affiliatewp';
	}

	public function label(): string {
		return 'AffiliateWP';
	}

	public function is_available(): bool {
		return function_exists( 'affiliate_wp' ) || class_exists( 'Affiliate_WP' );
	}

	public function supported_events(): array {
		return array( EventType::REGISTER );
	}

	protected function hooks(): void {
		add_action( 'affwp_affiliate_registered', array( $this, 'on_registered' ) );
	}

	/**
	 * @param mixed $affiliate_id
	 */
	public function on_registered( $affiliate_id ): void {
		$this->emit(
			array(
				'type'      => EventType::REGISTER,
				'entity_id' => is_scalar( $affiliate_id ) ? (string) $affiliate_id : '',
			)
		);
	}
}
