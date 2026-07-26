<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** The Newsletter Plugin signup forms, tracked client-side on submit. */
final class TheNewsletterPlugin extends AbstractSource {
	public function id(): string {
		return 'newsletter'; }
	public function label(): string {
		return 'The Newsletter Plugin'; }
	public function is_available(): bool {
		return defined( 'NEWSLETTER_VERSION' ) || class_exists( 'Newsletter' ); }
	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION ); }
	protected function hooks(): void {}
	public function client_selectors(): array {
		return array( 'form.tnp-form' ); }
}
