<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** Koko Analytics. Client-side events fired through the site's own Koko Analytics script. */
final class KokoAnalytics extends AbstractDestination {
	public function id(): string {
		return 'kokoanalytics'; }
	public function label(): string {
		return 'Koko Analytics'; }
	public function transports(): array {
		return array( self::TRANSPORT_CLIENT ); }
	public function is_configured(): bool {
		return true; }
	public function settings_fields(): array {
		return array(); }
}
