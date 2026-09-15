<?php

declare( strict_types=1 );

namespace WPConversionHub\Sources;

use WPConversionHub\Event\EventType;

/**
 * Video and audio plays. Entirely client-origin: bridge.js listens for the first
 * play on each <video>/<audio> element and posts it, so there are no PHP hooks.
 */
final class Media extends AbstractSource {

	public function id(): string {
		return 'media';
	}

	public function label(): string {
		return 'Media (video and audio)';
	}

	public function is_available(): bool {
		return true;
	}

	public function supported_events(): array {
		return array( EventType::PLAY );
	}

	protected function hooks(): void {
	}
}
