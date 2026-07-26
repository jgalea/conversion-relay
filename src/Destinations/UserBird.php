<?php

declare( strict_types=1 );

namespace WPConversionHub\Destinations;

/** UserBird Analytics. Client-side events fired through the site's own UserBird Analytics script. */
final class UserBird extends AbstractDestination {
	public function id(): string {
		return 'userbird'; }
	public function label(): string {
		return 'UserBird Analytics'; }
	public function transports(): array {
		return array( self::TRANSPORT_CLIENT ); }
	public function is_configured(): bool {
		return true; }
	public function settings_fields(): array {
		return array(); }
}
