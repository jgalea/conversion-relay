<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits Tutor LMS enrollments.
final class TutorLMS extends AbstractSource {

	public function id(): string {
		return 'tutorlms';
	}

	public function label(): string {
		return 'Tutor LMS';
	}

	public function is_available(): bool {
		return function_exists( 'tutor' ) || defined( 'TUTOR_VERSION' );
	}

	public function supported_events(): array {
		return array( EventType::ENROLL );
	}

	protected function hooks(): void {
		add_action( 'tutor_after_enrolled', array( $this, 'on_enrolled' ), 10, 3 );
	}

	/**
	 * @param mixed $course_id
	 * @param mixed $user_id
	 * @param mixed $enrol_id
	 */
	public function on_enrolled( $course_id, $user_id, $enrol_id ): void {
		$this->emit(
			array(
				'type'      => EventType::ENROLL,
				'entity_id' => is_scalar( $course_id ) ? (string) $course_id : '',
				'meta'      => array(
					'user_id'  => $user_id,
					'enrol_id' => $enrol_id,
				),
			)
		);
	}
}
