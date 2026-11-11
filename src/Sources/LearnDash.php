<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits LearnDash enrolment and course and lesson completion events.
final class LearnDash extends AbstractSource {

	public function id(): string {
		return 'learndash';
	}

	public function label(): string {
		return 'LearnDash';
	}

	public function is_available(): bool {
		return class_exists( 'SFWD_LMS' ) || defined( 'LEARNDASH_VERSION' );
	}

	public function supported_events(): array {
		return array( EventType::ENROLL, EventType::COMPLETION );
	}

	protected function hooks(): void {
		add_action( 'learndash_update_course_access', array( $this, 'on_access' ), 10, 4 );
		add_action( 'learndash_course_completed', array( $this, 'on_course' ) );
		add_action( 'learndash_lesson_completed', array( $this, 'on_lesson' ) );
	}

	/**
	 * @param mixed $user_id
	 * @param mixed $course_id
	 * @param mixed $access_list
	 * @param mixed $remove
	 */
	public function on_access( $user_id, $course_id, $access_list = null, $remove = false ): void {
		if ( $remove ) {
			return;
		}

		$this->emit(
			array(
				'type'      => EventType::ENROLL,
				'entity_id' => '',
				'meta'      => array(
					'object'    => 'course',
					'course_id' => $this->id_from_value( $course_id ),
					'user_id'   => $this->id_from_value( $user_id ),
				),
			)
		);
	}

	/**
	 * @param mixed $data
	 */
	public function on_course( $data ): void {
		if ( ! is_array( $data ) ) {
			return;
		}

		$this->emit(
			array(
				'type'      => EventType::COMPLETION,
				'entity_id' => '',
				'meta'      => array(
					'object'    => 'course',
					'course_id' => $this->id_from_value( $data['course'] ?? '' ),
					'user_id'   => $this->id_from_value( $data['user'] ?? '' ),
				),
			)
		);
	}

	/**
	 * @param mixed $data
	 */
	public function on_lesson( $data ): void {
		if ( ! is_array( $data ) ) {
			return;
		}

		$this->emit(
			array(
				'type'      => EventType::COMPLETION,
				'entity_id' => '',
				'meta'      => array(
					'object'    => 'lesson',
					'lesson_id' => $this->id_from_value( $data['lesson'] ?? '' ),
					'user_id'   => $this->id_from_value( $data['user'] ?? '' ),
				),
			)
		);
	}

	/**
	 * @param mixed $value
	 */
	private function id_from_value( $value ): string {
		if ( is_object( $value ) ) {
			$props = get_object_vars( $value );
			if ( isset( $props['ID'] ) && is_scalar( $props['ID'] ) ) {
				return (string) $props['ID'];
			}
		}

		return is_scalar( $value ) ? (string) $value : '';
	}
}
