<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** Mailchimp for WordPress signup forms, tracked client-side on submit. */
final class MC4WP extends AbstractSource {
	public function id(): string {
		return 'mc4wp'; }
	public function label(): string {
		return 'Mailchimp for WordPress'; }
	public function is_available(): bool {
		return function_exists( 'mc4wp' ) || defined( 'MC4WP_VERSION' ); }
	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION ); }
	protected function hooks(): void {}
	public function client_selectors(): array {
		return array( 'form.mc4wp-form' ); }
}
