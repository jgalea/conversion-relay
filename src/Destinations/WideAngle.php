<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** Wide Angle Analytics. Client-side events fired through the site's own Wide Angle Analytics script. */
final class WideAngle extends AbstractDestination {
	public function id(): string {
		return 'wideangle'; }
	public function label(): string {
		return 'Wide Angle Analytics'; }
	public function transports(): array {
		return array( self::TRANSPORT_CLIENT ); }
	public function is_configured(): bool {
		return true; }
	public function settings_fields(): array {
		return array(); }
}
