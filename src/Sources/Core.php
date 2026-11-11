<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/**
 * WordPress core events that need no third-party plugin: site search, user login,
 * and registration. Page views and engagement are client-origin (see bridge.js).
 */
final class Core extends AbstractSource {

	public function id(): string {
		return 'core';
	}

	public function label(): string {
		return 'WordPress Core';
	}

	public function is_available(): bool {
		return true;
	}

	public function supported_events(): array {
		return array( EventType::SEARCH, EventType::LOGIN, EventType::REGISTER );
	}

	protected function hooks(): void {
		add_action( 'template_redirect', array( $this, 'maybe_search' ) );
		add_action( 'wp_login', array( $this, 'on_login' ), 10, 2 );
		add_action( 'user_register', array( $this, 'on_register' ) );
	}

	public function maybe_search(): void {
		if ( ! is_search() ) {
			return;
		}
		$term = mb_substr( get_search_query(), 0, 100 );
		if ( '' === $term ) {
			return;
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$id = wp_hash( $ip . '|' . $term . '|' . gmdate( 'YmdH' ) );

		$this->emit_public(
			array(
				'type'      => EventType::SEARCH,
				'entity_id' => $id,
				'meta'      => array( 'search_term' => $term ),
			),
			$id
		);
	}

	/**
	 * @param string        $user_login
	 * @param \WP_User|null $user
	 */
	public function on_login( string $user_login, $user = null ): void {
		$this->emit(
			array(
				'type'      => EventType::LOGIN,
				'entity_id' => '',
				'meta'      => array( 'user_id' => $user instanceof \WP_User ? $user->ID : 0 ),
			)
		);
	}

	public function on_register( int $user_id ): void {
		$this->emit(
			array(
				'type'      => EventType::REGISTER,
				'entity_id' => (string) $user_id,
			)
		);
	}
}
