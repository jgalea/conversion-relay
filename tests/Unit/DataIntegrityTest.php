<?php

declare( strict_types=1 );

namespace WPConversionHub\Tests\Unit;

use WPConversionHub\Event\NormalizedEvent;
use WPConversionHub\Hub\ClientQueue;
use WPConversionHub\Sources\ContactForm7;
use WPConversionHub\Sources\Core;
use WPConversionHub\Sources\DownloadMonitor;
use WPConversionHub\Sources\GiveWP;
use WPConversionHub\Sources\MemberPress;
use WPConversionHub\Sources\WooCommerce;
use WPConversionHub\Sources\WPSimplePay;
use WPConversionHub\Storage\EventLog;

final class DataIntegrityTest extends TestCase {

	private function cf7_form(): object {
		return new class() {
			public function id(): int {
				return 5;
			}
			public function title(): string {
				return 'Contact';
			}
		};
	}

	public function testRepeatSubmissionsOfOneFormGetDistinctEventIds(): void {
		$source = new ContactForm7();
		$source->register( $this->dispatcher );

		$source->on_sent( $this->cf7_form() );
		$source->on_sent( $this->cf7_form() );

		$this->assertCount( 2, $this->events );
		$this->assertNotSame( $this->events[0]->event_id, $this->events[1]->event_id );
		$this->assertSame( '5', $this->events[0]->meta['form_id'] );
	}

	public function testAddToCartForTheSameProductIsNotCollapsed(): void {
		$source = new WooCommerce();
		$source->register( $this->dispatcher );

		$source->on_add_to_cart( 'k1', 77, 1, 0, null, null );
		$source->on_add_to_cart( 'k2', 77, 1, 0, null, null );

		$this->assertCount( 2, $this->events );
		$this->assertNotSame( $this->events[0]->event_id, $this->events[1]->event_id );
	}

	public function testSecondDeliveryOfTheSameFormIsStillQueued(): void {
		$this->configure( $this->metaServer(), array( 'consent' => array( 'ads_default' => 'granted' ) ) );
		$source = new ContactForm7();
		$source->register( $this->dispatcher );

		$source->on_sent( $this->cf7_form() );
		foreach ( $this->queued() as $args ) {
			$this->dispatcher->deliver( $args );
		}
		$source->on_sent( $this->cf7_form() );

		$this->assertCount( 2, $this->queued(), 'the second submission must reach the queue once the first was delivered' );
		$this->assertCount( 1, $GLOBALS['wpch_test_http'] );
	}

	public function testMetaGetsTheVisitorUserAgentAtDelivery(): void {
		$this->configure( $this->metaServer(), array( 'consent' => array( 'ads_default' => 'granted' ) ) );
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 AuditUA';

		$this->dispatcher->dispatch(
			NormalizedEvent::create(
				array(
					'type'   => 'form_submission',
					'source' => 'cf7',
				)
			)
		);

		$queued = $this->queued();
		$this->assertCount( 1, $queued );
		$this->assertStringNotContainsString( 'AuditUA', (string) wp_json_encode( $queued[0] ), 'queue args must not hold the user agent' );
		$this->assertStringNotContainsString( '203.0.113.9', (string) wp_json_encode( $queued[0] ), 'queue args must not hold the IP' );

		unset( $_SERVER['HTTP_USER_AGENT'] );
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$this->dispatcher->deliver( $queued[0] );

		$sent = json_decode( $GLOBALS['wpch_test_http'][0]['args']['body'], true );
		$user = $sent['data'][0]['user_data'];
		$this->assertSame( 'Mozilla/5.0 AuditUA', $user['client_user_agent'] );
		$this->assertSame( '203.0.113.9', $user['client_ip_address'], 'the cron request must not replace the visitor IP' );
	}

	public function testEmptyMetaCustomDataEncodesAsObject(): void {
		$this->configure( $this->metaServer(), array( 'consent' => array( 'ads_default' => 'granted' ) ) );
		$this->dispatcher->dispatch(
			NormalizedEvent::create(
				array(
					'type'   => 'form_submission',
					'source' => 'cf7',
				)
			)
		);
		$this->dispatcher->deliver( $this->queued()[0] );

		$this->assertStringContainsString( '"custom_data":{}', $GLOBALS['wpch_test_http'][0]['args']['body'] );
	}

	public function testClientOriginEventsAreNotReplayedOnTheNextPage(): void {
		$this->configure( $this->metaServer(), array( 'consent' => array( 'ads_default' => 'granted' ) ) );
		$this->dispatcher->dispatch(
			NormalizedEvent::create(
				array(
					'type'   => 'play',
					'source' => 'client',
					'origin' => 'client',
				)
			)
		);
		$this->assertSame( array(), ClientQueue::flush(), 'the browser already fired the pixel' );

		// Control: the same destination does queue a server-origin event for the browser.
		$this->dispatcher->dispatch(
			NormalizedEvent::create(
				array(
					'type'   => 'form_submission',
					'source' => 'cf7',
					'origin' => 'server',
					'meta'   => array(),
				)
			)
		);
		$this->assertNotEmpty( $this->queued() );
	}

	public function testGoogleAdsOnlyCountsPurchasesByDefault(): void {
		$this->configure(
			array(
				'google_ads' => array(
					'enabled'       => true,
					'conversion_id' => 'AW-1',
				),
			),
			array( 'consent' => array( 'ads_default' => 'granted' ) )
		);

		foreach ( array( 'search', 'login', 'add_to_cart', 'form_submission' ) as $type ) {
			$this->dispatcher->dispatch(
				NormalizedEvent::create(
					array(
						'type'   => $type,
						'source' => 'cf7',
						'origin' => 'server',
					)
				)
			);
		}
		$this->assertSame( array(), ClientQueue::flush() );

		$this->dispatcher->dispatch(
			NormalizedEvent::create(
				array(
					'type'      => 'purchase',
					'source'    => 'cf7',
					'entity_id' => '9',
					'origin'    => 'server',
				)
			)
		);
		$this->assertCount( 1, ClientQueue::flush() );
	}

	public function testEventSelectionOverridesTheDefault(): void {
		$this->configure(
			array(
				'google_ads' => array(
					'enabled'       => true,
					'conversion_id' => 'AW-1',
					'events'        => array( 'form_submission' ),
				),
			),
			array( 'consent' => array( 'ads_default' => 'granted' ) )
		);
		$this->dispatcher->dispatch(
			NormalizedEvent::create(
				array(
					'type'   => 'form_submission',
					'source' => 'cf7',
					'origin' => 'server',
				)
			)
		);
		$this->dispatcher->dispatch(
			NormalizedEvent::create(
				array(
					'type'      => 'purchase',
					'source'    => 'cf7',
					'entity_id' => '9',
					'origin'    => 'server',
				)
			)
		);

		$this->assertCount( 1, ClientQueue::flush() );
	}

	public function testAttemptsSurviveAHighVolumeLog(): void {
		$this->configure( $this->metaServer(), array( 'consent' => array( 'ads_default' => 'granted' ) ) );
		$GLOBALS['wpch_test_http_code'] = 500;
		$this->dispatcher->dispatch(
			NormalizedEvent::create(
				array(
					'type'   => 'form_submission',
					'source' => 'cf7',
				)
			)
		);
		$first = $this->queued()[0];

		for ( $i = 0; $i < 300; $i++ ) {
			EventLog::record_pending( 'filler' . $i, 'webhook', 'search', 'core', '', array() );
		}

		for ( $round = 1; $round <= EventLog::MAX_ATTEMPTS; $round++ ) {
			$this->dispatcher->deliver( $first );
		}

		$this->assertCount( EventLog::MAX_ATTEMPTS, $GLOBALS['wpch_test_http'], 'every attempt goes out' );
		$this->assertCount( EventLog::MAX_ATTEMPTS - 1 + 1, $GLOBALS['wpch_test_scheduled'], 'one initial delivery plus retries up to the cap, then it stops' );
		$this->assertSame( EventLog::MAX_ATTEMPTS, EventLog::attempts( $first['event']['event_id'], 'meta' ) );
		$row = EventLog::recent( 1, EventLog::STATUS_DEAD );
		$this->assertCount( 1, $row, 'the row dead-letters instead of retrying forever' );
	}

	public function testRefiredHookDoesNotQueueASecondDeliveryWhilePending(): void {
		$this->assertTrue( EventLog::record_pending( 'e1', 'meta', 'purchase', 'woocommerce', '1', array() ) );
		$this->assertFalse( EventLog::record_pending( 'e1', 'meta', 'purchase', 'woocommerce', '1', array() ) );
		EventLog::mark_failed( 'e1', 'meta', 'boom', 1 );
		$this->assertFalse( EventLog::record_pending( 'e1', 'meta', 'purchase', 'woocommerce', '1', array() ) );
	}

	public function testSearchIsDedupedCappedAndRateLimited(): void {
		$source = new Core();
		$source->register( $this->dispatcher );

		$GLOBALS['wpch_test_search'] = 'shoes';
		$source->maybe_search();
		$source->maybe_search();
		$this->assertCount( 1, $this->events, 'a reload of the same search is one event' );

		$GLOBALS['wpch_test_search'] = str_repeat( 'a', 500 );
		$source->maybe_search();
		$this->assertSame( 100, mb_strlen( $this->events[1]->meta['search_term'] ) );

		wpch_test_filter( 'wpch_rest_rate_limit', static fn() => 3 );
		foreach ( range( 1, 20 ) as $n ) {
			$GLOBALS['wpch_test_search'] = 'q' . $n;
			$source->maybe_search();
		}
		$this->assertLessThanOrEqual( 3, count( $this->events ), 'searches share the REST budget' );
	}

	public function testDroppedPublicEventsLeaveNoDedupeRows(): void {
		$source = new Core();
		$source->register( $this->dispatcher );

		wpch_test_filter( 'wpch_rest_rate_limit', static fn() => 3 );
		foreach ( range( 1, 50 ) as $n ) {
			$GLOBALS['wpch_test_search'] = 'q' . $n;
			$source->maybe_search();
		}

		$once = array_filter( array_keys( $GLOBALS['wpch_test_transients'] ), static fn( $k ) => 0 === strpos( $k, 'wpch_once_' ) );
		$this->assertCount( 3, $once, 'only events that passed the limiter write a dedupe row' );
	}

	public function testAnonymousAddToCartAndDownloadsAreDedupedAndRateLimited(): void {
		$woo = new WooCommerce();
		$woo->register( $this->dispatcher );
		$dlm = new DownloadMonitor();
		$dlm->register( $this->dispatcher );

		$woo->on_add_to_cart( 'abc', 5, 1, 0, null, null );
		$woo->on_add_to_cart( 'abc', 5, 1, 0, null, null );
		$this->assertCount( 1, $this->events, 'the same cart line is one event' );

		$download = new class() {
			public function get_id(): int {
				return 9;
			}
			public function get_title(): string {
				return 'File';
			}
		};
		$dlm->on_download( $download );
		$dlm->on_download( $download );
		$this->assertCount( 2, $this->events, 'the same download from one visitor is one event' );

		wpch_test_filter( 'wpch_rest_rate_limit', static fn() => 5 );
		foreach ( range( 1, 40 ) as $n ) {
			$woo->on_add_to_cart( 'k' . $n, 5, 1, 0, null, null );
		}
		$this->assertLessThanOrEqual( 5, count( $this->events ), 'add-to-cart floods share the budget' );
	}

	public function testWebhookAndAdminFiredMoneySourcesTakeTheNoDecisionPath(): void {
		$this->configure( $this->metaServer() );
		$_COOKIE                    = array( '_fbp' => 'fb.1.admin' );
		$_SERVER['HTTP_USER_AGENT'] = 'StripeWebhook';

		$simple = new WPSimplePay();
		$simple->register( $this->dispatcher );
		$simple->on_paid( (object) array( 'data' => (object) array( 'object' => (object) array( 'id' => 'pi_1', 'amount' => 1000, 'currency' => 'eur' ) ) ), null );

		$member = new MemberPress();
		$member->register( $this->dispatcher );
		$member->on_complete( (object) array( 'id' => 7, 'amount' => 10, 'user_email' => 'a@example.com' ) );

		$give = new GiveWP();
		$give->register( $this->dispatcher );
		$give->on_complete( 31 );

		$this->assertCount( 3, $this->events );
		foreach ( $this->events as $event ) {
			$this->assertSame( array(), $event->identity, $event->source . ' must not read the triggering request' );
			$this->assertNull( $event->consent );
		}
		$this->assertSame( array(), $this->queued(), 'ads are denied when there is no stored decision' );
	}

	public function testStatusFiltersAndRetryOnlyTouchRowsThatAreNotWaiting(): void {
		EventLog::record_pending( 'a', 'meta', 'purchase', 'woocommerce', '1', array( 'type' => 'purchase', 'source' => 'woocommerce' ) );
		EventLog::record_pending( 'b', 'ga4', 'purchase', 'woocommerce', '2', array( 'type' => 'purchase', 'source' => 'woocommerce' ) );
		EventLog::record_pending( 'c', 'meta', 'purchase', 'woocommerce', '3', array( 'type' => 'purchase', 'source' => 'woocommerce' ) );
		EventLog::mark_failed( 'a', 'meta', 'boom', 1 );
		EventLog::mark_failed( 'b', 'ga4', 'boom', EventLog::MAX_ATTEMPTS );
		EventLog::mark_sent( 'c', 'meta' );

		$this->assertSame( 3, EventLog::count( '', '' ) );
		$this->assertSame( 2, EventLog::count( '', 'meta' ) );
		$this->assertSame( 1, EventLog::count( 'dead', '' ) );
		$this->assertCount( 1, EventLog::page( 'sent', 'meta', 25, 0 ) );

		// 'a' just failed and has a backoff action pending, so only the dead row is stuck.
		$stuck = EventLog::stuck();
		$this->assertCount( 1, $stuck );
		$this->assertSame( 'b', $stuck[0]['event_id'] );

		$this->assertSame( 1, \WPConversionHub\Hub\Queue::retry_rows( $stuck ) );
		$this->assertSame( 0, EventLog::attempts( 'b', 'ga4' ) );
		$this->assertCount( 1, $GLOBALS['wpch_test_scheduled'] );
	}
}
