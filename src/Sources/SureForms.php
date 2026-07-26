<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** SureForms forms, tracked client-side on submit (no reliable server hook). */
final class SureForms extends AbstractSource {

	public function id(): string {
		return 'sureforms';
	}

	public function label(): string {
		return 'SureForms';
	}

	public function is_available(): bool {
		return defined( 'SRFM_VER' ) || class_exists( 'SRFM\\Inc\\Plugin_Loader' );
	}

	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION );
	}

	protected function hooks(): void {}

	public function client_selectors(): array {
		return array( 'form.srfm-form' );
	}
}
