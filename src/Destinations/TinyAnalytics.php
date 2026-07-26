<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** TinyAnalytics. Client-side events fired through the site's own TinyAnalytics script. */
final class TinyAnalytics extends AbstractDestination {
	public function id(): string {
		return 'tinyanalytics'; }
	public function label(): string {
		return 'TinyAnalytics'; }
	public function transports(): array {
		return array( self::TRANSPORT_CLIENT ); }
	public function is_configured(): bool {
		return true; }
	public function settings_fields(): array {
		return array(); }
}
