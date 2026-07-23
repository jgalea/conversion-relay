<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits GiveWP donation completions.
final class GiveWP extends AbstractSource {

	public function id(): string {
		return 'givewp';
	}

	public function label(): string {
		return 'GiveWP';
	}

	public function is_available(): bool {
		return class_exists( 'Give' ) || function_exists( 'give_get_payment_amount' );
	}

	public function supported_events(): array {
		return array( EventType::DONATION );
	}

	protected function hooks(): void {
		add_action( 'give_complete_donation', array( $this, 'on_complete' ) );
	}

	/**
	 * @param mixed $payment_id
	 */
	public function on_complete( $payment_id ): void {
		$value = null;
		if ( function_exists( 'give_donation_amount' ) ) {
			$value = (float) give_donation_amount( $payment_id );
		} elseif ( function_exists( 'give_get_payment_amount' ) ) {
			$value = (float) give_get_payment_amount( $payment_id );
		}

		$currency = function_exists( 'give_get_payment_currency_code' )
			? give_get_payment_currency_code( $payment_id )
			: null;

		$user_data = array();
		if ( function_exists( 'give_get_payment_user_email' ) ) {
			$email = give_get_payment_user_email( $payment_id );
			if ( is_string( $email ) && '' !== $email ) {
				$user_data['email'] = $email;
			}
		}

		$this->emit(
			array(
				'type'      => EventType::DONATION,
				'entity_id' => (string) $payment_id,
				'value'     => $value,
				'currency'  => $currency,
				'user_data' => $user_data,
			)
		);
	}
}
