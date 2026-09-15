<?php

declare( strict_types=1 );

namespace WPConversionHub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPConversionHub\Event\NormalizedEvent;
use WPConversionHub\Hub\Dispatcher;
use WPConversionHub\Support\Settings;

/**
 * Action Scheduler keeps a completed action's args for a month and a failed
 * one's for three, so whatever the dispatcher enqueues is stored, not handed
 * over. Raw customer data must never reach it.
 */
final class QueuePayloadTest extends TestCase {

	private const RAW_EMAIL = 'Buyer@Example.COM';
	private const RAW_PHONE = '+44 7700 900123';

	private function event(): NormalizedEvent {
		return NormalizedEvent::create(
			array(
				'type'      => 'purchase',
				'source'    => 'woocommerce',
				'origin'    => 'server',
				'value'     => 42.5,
				'entity_id' => '1001',
				'user_data' => array(
					'email' => self::RAW_EMAIL,
					'phone' => self::RAW_PHONE,
				),
				'identity'  => array(
					'ip'         => '203.0.113.9',
					'user_agent' => 'Mozilla/5.0 (test)',
					'client_id'  => 'abc123',
				),
			)
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function payload( NormalizedEvent $event ): array {
		$method = new \ReflectionMethod( Dispatcher::class, 'queue_payload' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true ); // Required before 8.1, a no-op after.
		}
		return (array) $method->invoke( null, $event );
	}

	private function setEnhanced( bool $on ): void {
		Settings::save( array( 'enhanced_conversions' => $on ) );
	}

	public function testRawEmailNeverReachesTheQueue(): void {
		$this->setEnhanced( true );
		$event = $this->event();

		// Control: the event itself does carry the raw address, so a pass below
		// means the payload was scrubbed, not that the fixture was empty.
		$raw = (string) wp_json_encode_fallback( $event->to_array() );
		$this->assertStringContainsString( 'Buyer@Example.COM', $raw );

		$encoded = (string) wp_json_encode_fallback( $this->payload( $event ) );

		$this->assertStringNotContainsString( 'Buyer@Example.COM', $encoded );
		$this->assertStringNotContainsString( 'buyer@example.com', $encoded );
		$this->assertStringNotContainsString( '7700900123', $encoded );
	}

	public function testUserDataIsHashedWhenEnhancedConversionsAreOn(): void {
		$this->setEnhanced( true );
		$payload = $this->payload( $this->event() );

		$this->assertSame( hash( 'sha256', 'buyer@example.com' ), $payload['user_data']['email'] );
		$this->assertSame( hash( 'sha256', '447700900123' ), $payload['user_data']['phone'] );
	}

	public function testUserDataIsDroppedWhenEnhancedConversionsAreOff(): void {
		$this->setEnhanced( false );
		$payload = $this->payload( $this->event() );

		$this->assertSame( array(), $payload['user_data'] );
	}

	public function testUserAgentIsNotQueued(): void {
		$this->setEnhanced( true );
		$payload = $this->payload( $this->event() );

		$this->assertArrayNotHasKey( 'user_agent', $payload['identity'] );
		// The IP survives: destinations that need it are sending it on anyway.
		$this->assertSame( '203.0.113.9', $payload['identity']['ip'] );
	}

	/**
	 * The retry path re-serialises an event rebuilt from an already-hashed
	 * payload, so hashing twice has to be a no-op or retries would send garbage.
	 */
	public function testHashingIsIdempotentAcrossRetries(): void {
		$this->setEnhanced( true );
		$first  = $this->payload( $this->event() );
		$second = $this->payload( NormalizedEvent::create( $first ) );

		$this->assertSame( $first['user_data'], $second['user_data'] );
	}
}
