<?php

declare( strict_types=1 );

namespace WPConversionHub\Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use WPConversionHub\Destinations\Ga4;
use WPConversionHub\Destinations\GoogleAds;
use WPConversionHub\Destinations\MetaAds;
use WPConversionHub\Destinations\Webhook;
use WPConversionHub\Event\NormalizedEvent;
use WPConversionHub\Hub\ClientQueue;
use WPConversionHub\Hub\Dispatcher;
use WPConversionHub\Support\Registry;
use WPConversionHub\Support\Settings;

abstract class TestCase extends BaseTestCase {

	protected Dispatcher $dispatcher;

	/** @var NormalizedEvent[] */
	protected array $events = array();

	protected function setUp(): void {
		wpch_test_reset();
		$this->events = array();

		$registry = new Registry();
		$registry->add_destination( new MetaAds() );
		$registry->add_destination( new GoogleAds() );
		$registry->add_destination( new Ga4() );
		$registry->add_destination( new Webhook() );
		$this->dispatcher = new Dispatcher( $registry );

		$ref = new \ReflectionProperty( ClientQueue::class, 'request_buffer' );
		if ( PHP_VERSION_ID < 80100 ) {
			$ref->setAccessible( true );
		}
		$ref->setValue( null, array() );

		wpch_test_filter(
			'wpch_event',
			function ( $event ) {
				$this->events[] = $event;
				return $event;
			}
		);
	}

	/**
	 * @param array<string,mixed> $destinations
	 * @param array<string,mixed> $extra
	 */
	protected function configure( array $destinations, array $extra = array() ): void {
		$settings                 = array_merge( Settings::defaults(), $extra );
		$settings['destinations'] = $destinations;
		Settings::save( $settings );
	}

	protected function metaServer(): array {
		return array(
			'meta' => array(
				'enabled'      => true,
				'pixel_id'     => '123',
				'access_token' => 'tok',
			),
		);
	}

	/**
	 * The args of every delivery the dispatcher queued, as the worker will receive them.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function queued(): array {
		return array_map( static fn( array $s ): array => $s['args'][0], $GLOBALS['wpch_test_scheduled'] );
	}
}
