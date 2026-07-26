<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits LifterLMS course and lesson completion events.
final class LifterLMS extends AbstractSource {

	public function id(): string {
		return 'lifterlms';
	}

	public function label(): string {
		return 'LifterLMS';
	}

	public function is_available(): bool {
		return class_exists( 'LifterLMS' ) || function_exists( 'llms' );
	}

	public function supported_events(): array {
		return array( EventType::ENROLL );
	}

	protected function hooks(): void {
		add_action( 'lifterlms_course_completed', array( $this, 'on_course' ), 10, 2 );
		add_action( 'lifterlms_lesson_completed', array( $this, 'on_lesson' ), 10, 2 );
	}

	/**
	 * @param mixed $user_id
	 * @param mixed $course_id
	 */
	public function on_course( $user_id, $course_id ): void {
		$this->emit(
			array(
				'type'      => EventType::ENROLL,
				'entity_id' => (string) $course_id,
				'meta'      => array(
					'object'  => 'course',
					'user_id' => $user_id,
				),
			)
		);
	}

	/**
	 * @param mixed $user_id
	 * @param mixed $lesson_id
	 */
	public function on_lesson( $user_id, $lesson_id ): void {
		$this->emit(
			array(
				'type'      => EventType::ENROLL,
				'entity_id' => (string) $lesson_id,
				'meta'      => array(
					'object'  => 'lesson',
					'user_id' => $user_id,
				),
			)
		);
	}
}
