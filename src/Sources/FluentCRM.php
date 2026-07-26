<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits FluentCRM contact creation as subscribe events.
final class FluentCRM extends AbstractSource {

	public function id(): string {
		return 'fluentcrm';
	}

	public function label(): string {
		return 'FluentCRM';
	}

	public function is_available(): bool {
		return defined( 'FLUENTCRM' ) || function_exists( 'FluentCrmApi' );
	}

	public function supported_events(): array {
		return array( EventType::SUBSCRIBE );
	}

	protected function hooks(): void {
		add_action( 'fluent_crm/contact_created', array( $this, 'on_created' ) );
	}

	/**
	 * @param mixed $subscriber FluentCRM subscriber object when the plugin is loaded.
	 */
	public function on_created( $subscriber ): void {
		if ( ! is_object( $subscriber ) ) {
			return;
		}

		$user_data = array();
		if ( isset( $subscriber->email ) && is_string( $subscriber->email ) && '' !== $subscriber->email ) {
			$user_data['email'] = $subscriber->email;
		}

		$this->emit(
			array(
				'type'      => EventType::SUBSCRIBE,
				'entity_id' => isset( $subscriber->id ) ? (string) $subscriber->id : '',
				'user_data' => $user_data,
			)
		);
	}
}
