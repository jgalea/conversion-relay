<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** Mailster signup forms, tracked client-side on submit. */
final class Mailster extends AbstractSource {
	public function id(): string {
		return 'mailster'; }
	public function label(): string {
		return 'Mailster'; }
	public function is_available(): bool {
		return defined( 'MAILSTER_VERSION' ) || function_exists( 'mailster' ); }
	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION ); }
	protected function hooks(): void {}
	public function client_selectors(): array {
		return array( 'form.mailster-form' ); }
}
