<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits FunnelKit Automations contact subscribe events.
final class FunnelKitAutomations extends AbstractSource {

	public function id(): string {
		return 'funnelkit';
	}

	public function label(): string {
		return 'FunnelKit Automations';
	}

	public function is_available(): bool {
		return defined( 'BWFAN_VERSION' ) || class_exists( 'BWFAN_Core' );
	}

	public function supported_events(): array {
		return array( EventType::SUBSCRIBE );
	}

	protected function hooks(): void {
		add_action( 'bwfcrm_after_contact_subscribed', array( $this, 'on_subscribed' ) );
	}

	/**
	 * @param mixed $contact FunnelKit contact object when the plugin is loaded.
	 */
	public function on_subscribed( $contact ): void {
		if ( ! is_object( $contact ) ) {
			return;
		}

		$this->emit(
			array(
				'type'      => EventType::SUBSCRIBE,
				'entity_id' => isset( $contact->id ) ? (string) $contact->id : '',
			)
		);
	}
}
