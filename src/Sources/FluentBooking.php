<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits FluentBooking scheduled bookings.
final class FluentBooking extends AbstractSource {

	public function id(): string {
		return 'fluentbooking';
	}

	public function label(): string {
		return 'FluentBooking';
	}

	public function is_available(): bool {
		return defined( 'FLUENT_BOOKING_VERSION' ) || function_exists( 'FluentBooking' );
	}

	public function supported_events(): array {
		return array( EventType::BOOKING );
	}

	protected function hooks(): void {
		add_action( 'fluent_booking/after_booking_scheduled', array( $this, 'on_booking' ), 10, 2 );
	}

	/**
	 * @param mixed $booking
	 * @param mixed $calendar_event
	 */
	public function on_booking( $booking, $calendar_event ): void {
		if ( ! is_object( $booking ) ) {
			return;
		}

		$this->emit(
			array(
				'type'      => EventType::BOOKING,
				'entity_id' => isset( $booking->id ) ? (string) $booking->id : '',
			)
		);
	}
}
