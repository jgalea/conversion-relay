<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits MemberPress subscription completions from completed transactions.
final class MemberPress extends AbstractSource {

	public function id(): string {
		return 'memberpress';
	}

	public function label(): string {
		return 'MemberPress';
	}

	public function is_available(): bool {
		return class_exists( 'MeprCtrlFactory' ) || defined( 'MEPR_VERSION' );
	}

	public function supported_events(): array {
		return array( EventType::SUBSCRIBE );
	}

	protected function hooks(): void {
		add_action( 'mepr_txn_status_complete', array( $this, 'on_complete' ) );
	}

	/**
	 * @param mixed $txn MeprTransaction object when MemberPress is loaded.
	 */
	public function on_complete( $txn ): void {
		if ( ! is_object( $txn ) ) {
			return;
		}

		$email = '';
		if ( isset( $txn->user_email ) && is_string( $txn->user_email ) && '' !== $txn->user_email ) {
			$email = $txn->user_email;
		} elseif ( method_exists( $txn, 'get_email' ) ) {
			$maybe = $txn->get_email();
			if ( is_string( $maybe ) && '' !== $maybe ) {
				$email = $maybe;
			}
		}

		$user_data = array();
		if ( '' !== $email ) {
			$user_data['email'] = $email;
		}

		$this->emit(
			array(
				'type'      => EventType::SUBSCRIBE,
				'entity_id' => isset( $txn->id ) ? (string) $txn->id : '',
				'value'     => isset( $txn->amount ) ? (float) $txn->amount : null,
				'currency'  => function_exists( 'mepr_get_currency' ) ? mepr_get_currency() : null,
				'user_data' => $user_data,
			)
		);
	}
}
