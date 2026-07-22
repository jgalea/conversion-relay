<?php

declare( strict_types=1 );

namespace WPConversionHub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPConversionHub\Event\EventType;
use WPConversionHub\Event\NormalizedEvent;

final class NormalizedEventTest extends TestCase {

	public function test_event_id_is_deterministic_for_the_same_entity(): void {
		$a = NormalizedEvent::create( [ 'type' => EventType::PURCHASE, 'source' => 'woocommerce', 'entity_id' => '42' ] );
		$b = NormalizedEvent::create( [ 'type' => EventType::PURCHASE, 'source' => 'woocommerce', 'entity_id' => '42' ] );

		$this->assertSame( $a->event_id, $b->event_id, 'Same source/type/entity must mint the same event id so retries dedupe.' );
	}

	public function test_event_id_differs_across_entities(): void {
		$a = NormalizedEvent::create( [ 'type' => EventType::PURCHASE, 'source' => 'woocommerce', 'entity_id' => '42' ] );
		$b = NormalizedEvent::create( [ 'type' => EventType::PURCHASE, 'source' => 'woocommerce', 'entity_id' => '43' ] );

		$this->assertNotSame( $a->event_id, $b->event_id );
	}

	public function test_validity_requires_known_type_and_source(): void {
		$valid   = NormalizedEvent::create( [ 'type' => EventType::SEARCH, 'source' => 'core' ] );
		$no_type = NormalizedEvent::create( [ 'type' => 'nope', 'source' => 'core' ] );
		$no_src  = NormalizedEvent::create( [ 'type' => EventType::SEARCH, 'source' => '' ] );

		$this->assertTrue( $valid->is_valid() );
		$this->assertFalse( $no_type->is_valid() );
		$this->assertFalse( $no_src->is_valid() );
	}

	public function test_round_trips_through_array(): void {
		$event = NormalizedEvent::create(
			[
				'type'      => EventType::PURCHASE,
				'source'    => 'edd',
				'entity_id' => '7',
				'value'     => 19.99,
				'currency'  => 'EUR',
				'items'     => [ [ 'item_id' => 1 ] ],
			]
		);

		$restored = NormalizedEvent::from_array( $event->to_array() );

		$this->assertSame( $event->event_id, $restored->event_id );
		$this->assertSame( 19.99, $restored->value );
		$this->assertSame( 'EUR', $restored->currency );
		$this->assertSame( [ [ 'item_id' => 1 ] ], $restored->items );
	}
}
