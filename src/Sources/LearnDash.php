<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

// Emits LearnDash course and lesson completion events.
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
		return array( EventType::ENROLL );
	}

	protected function hooks(): void {
		add_action( 'learndash_course_completed', array( $this, 'on_course' ) );
		add_action( 'learndash_lesson_completed', array( $this, 'on_lesson' ) );
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
				'type'      => EventType::ENROLL,
				'entity_id' => $this->id_from_value( $data['course'] ?? '' ),
				'meta'      => array(
					'object'  => 'course',
					'user_id' => $this->id_from_value( $data['user'] ?? '' ),
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
				'type'      => EventType::ENROLL,
				'entity_id' => $this->id_from_value( $data['lesson'] ?? '' ),
				'meta'      => array(
					'object'  => 'lesson',
					'user_id' => $this->id_from_value( $data['user'] ?? '' ),
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
