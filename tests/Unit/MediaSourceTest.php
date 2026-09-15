<?php

declare( strict_types=1 );

namespace WPConversionHub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPConversionHub\Event\EventType;
use WPConversionHub\Sources\Media;

/**
 * Media tracking is client-origin only, so nothing server-side proves it exists.
 * These guard the contract bridge.js depends on: a 'media' source to toggle, and
 * 'play' staying acceptable at the public REST endpoint.
 */
final class MediaSourceTest extends TestCase {

	public function test_media_source_id_is_stable(): void {
		$media = new Media();
		$this->assertSame( 'media', $media->id() );
	}

	public function test_media_source_reports_play(): void {
		$media = new Media();
		$this->assertContains( EventType::PLAY, $media->supported_events() );
	}

	public function test_media_source_needs_no_third_party_plugin(): void {
		$media = new Media();
		$this->assertTrue( $media->is_available() );
	}

	public function test_play_is_accepted_as_client_origin(): void {
		$this->assertContains( EventType::PLAY, EventType::client_origin() );
	}
}
