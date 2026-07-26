<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/** MailPoet signup forms, tracked client-side on submit. */
final class MailPoet extends AbstractSource {
	public function id(): string {
		return 'mailpoet'; }
	public function label(): string {
		return 'MailPoet'; }
	public function is_available(): bool {
		return class_exists( 'MailPoet\\DI\\ContainerWrapper' ) || defined( 'MAILPOET_VERSION' ); }
	public function supported_events(): array {
		return array( EventType::FORM_SUBMISSION ); }
	protected function hooks(): void {}
	public function client_selectors(): array {
		return array( 'form.mailpoet_form' ); }
}
