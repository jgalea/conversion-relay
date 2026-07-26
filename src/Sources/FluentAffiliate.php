<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Fluent Affiliate creation as register events.
final class FluentAffiliate extends AbstractSource {

	public function id(): string {
		return 'fluentaffiliate';
	}

	public function label(): string {
		return 'Fluent Affiliate';
	}

	public function is_available(): bool {
		return defined( 'FLUENT_AFFILIATE_VERSION' ) || function_exists( 'FluentAffiliate' );
	}

	public function supported_events(): array {
		return array( EventType::REGISTER );
	}

	protected function hooks(): void {
		add_action( 'fluent_affiliate/affiliate_created', array( $this, 'on_created' ) );
	}

	/**
	 * @param mixed $affiliate Fluent Affiliate object when the plugin is loaded.
	 */
	public function on_created( $affiliate ): void {
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
