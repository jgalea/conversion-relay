<?php

declare( strict_types=1 );

namespace WPConversionHub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPConversionHub\Event\EventType;

/**
 * Guards the public REST endpoint's boundary: money events must never be
 * accepted as client-origin, or an anonymous browser could fabricate conversions.
 */
final class ClientOriginTest extends TestCase {

	/**
	 * @return array<string,array{0:string}>
	 */
	public function moneyEventProvider(): array {
		return array(
			'purchase'       => array( EventType::PURCHASE ),
			'donation'       => array( EventType::DONATION ),
			'refund'         => array( EventType::REFUND ),
			'subscribe'      => array( EventType::SUBSCRIBE ),
			'begin_checkout' => array( EventType::BEGIN_CHECKOUT ),
		);
	}

	/**
	 * @dataProvider moneyEventProvider
	 */
	public function test_money_events_are_not_client_origin( string $type ): void {
		$this->assertNotContains( $type, EventType::client_origin() );
	}

	public function test_engagement_events_are_client_origin(): void {
		foreach ( array( EventType::SCROLL, EventType::TIME_ON_PAGE, EventType::PLAY, EventType::CLICK ) as $type ) {
			$this->assertContains( $type, EventType::client_origin() );
		}
	}
}
