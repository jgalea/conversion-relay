<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits LatePoint booking creations.
final class LatePoint extends AbstractSource {

	public function id(): string {
		return 'latepoint';
	}

	public function label(): string {
		return 'LatePoint';
	}

	public function is_available(): bool {
		return defined( 'LATEPOINT_VERSION' ) || class_exists( 'OsBookingModel' );
	}

	public function supported_events(): array {
		return array( EventType::BOOKING );
	}

	protected function hooks(): void {
		add_action( 'latepoint_booking_created', array( $this, 'on_booking' ) );
	}

	/**
	 * @param mixed $booking
	 */
	public function on_booking( $booking ): void {
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
