<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Groundhogg contact creation as subscribe events.
final class Groundhogg extends AbstractSource {

	public function id(): string {
		return 'groundhogg';
	}

	public function label(): string {
		return 'Groundhogg';
	}

	public function is_available(): bool {
		return defined( 'GROUNDHOGG_VERSION' ) || function_exists( 'Groundhogg\\get_db' );
	}

	public function supported_events(): array {
		return array( EventType::SUBSCRIBE );
	}

	protected function hooks(): void {
		add_action( 'groundhogg/contact/post_create', array( $this, 'on_created' ), 10, 2 );
	}

	/**
	 * @param mixed $id      Contact ID.
	 * @param mixed $contact Groundhogg contact object when the plugin is loaded.
	 */
	public function on_created( $id, $contact = null ): void {
		$user_data = array();

		if ( is_object( $contact ) ) {
			if ( method_exists( $contact, 'get_email' ) ) {
				$email = $contact->get_email();
				if ( is_string( $email ) && '' !== $email ) {
					$user_data['email'] = $email;
				}
			} elseif ( isset( $contact->email ) && is_string( $contact->email ) && '' !== $contact->email ) {
				$user_data['email'] = $contact->email;
			}

			if ( method_exists( $contact, 'get_first_name' ) ) {
				$first = $contact->get_first_name();
				if ( is_string( $first ) && '' !== $first ) {
					$user_data['first_name'] = $first;
				}
			}

			if ( method_exists( $contact, 'get_last_name' ) ) {
				$last = $contact->get_last_name();
				if ( is_string( $last ) && '' !== $last ) {
					$user_data['last_name'] = $last;
				}
			}

			if ( method_exists( $contact, 'get_phone_number' ) ) {
				$phone = $contact->get_phone_number();
				if ( is_string( $phone ) && '' !== $phone ) {
					$user_data['phone'] = $phone;
				}
			}
		}

		$this->emit(
			array(
				'type'      => EventType::SUBSCRIBE,
				'entity_id' => (string) $id,
				'user_data' => $user_data,
			)
		);
	}
}
