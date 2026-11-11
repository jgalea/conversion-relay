<?php

declare( strict_types=1 );

namespace WPConversionHub\Tests\Unit;

use WPConversionHub\Destinations\Webhook;
use WPConversionHub\Event\NormalizedEvent;

final class PrivacyTest extends TestCase {

	private function webhook_body( bool $enhanced ): array {
		$this->configure(
			array(
				'webhook' => array(
					'enabled' => true,
					'url'     => 'https://93.184.216.34/hook',
				),
			),
			array( 'enhanced_conversions' => $enhanced )
		);
		$event  = NormalizedEvent::create(
			array(
				'type'     => 'purchase',
				'source'   => 'woocommerce',
				'identity' => array(
					'ip'         => '198.51.100.7',
					'user_agent' => 'UA',
					'fbp'        => 'fb.1.x',
					'gclid'      => 'g1',
					'client_id'  => '1.2',
				),
			)
		);
		$result = ( new Webhook() )->send_server( $event );
		$this->assertTrue( $result->ok() );

		return json_decode( $GLOBALS['wpch_test_http'][0]['args']['body'], true );
	}

	public function testWebhookOmitsIpAndClickIdsWithoutTheEnhancedOptIn(): void {
		$identity = $this->webhook_body( false )['identity'];

		$this->assertSame( array( 'client_id' => '1.2' ), $identity );
	}

	public function testWebhookKeepsIpAndClickIdsWithTheOptIn(): void {
		$identity = $this->webhook_body( true )['identity'];

		$this->assertSame( '198.51.100.7', $identity['ip'] );
		$this->assertSame( 'g1', $identity['gclid'] );
		$this->assertArrayNotHasKey( 'user_agent', $identity );
	}

	public function testOrderSnapshotIsExportedAndErasedByBillingEmail(): void {
		$order = new FakeOrder( 77 );
		$order->update_meta_data( '_wpch_visitor', wp_json_encode( array( 'identity' => array( 'ip' => '198.51.100.7', 'user_agent' => 'BuyerUA' ) ) ) );
		$GLOBALS['wpch_test_orders_by_email']['buyer@example.com'] = array( $order );

		$export = \WPConversionHub\Support\Privacy::export( 'buyer@example.com' );
		$this->assertCount( 1, $export['data'] );
		$this->assertSame( array( 'name' => 'ip', 'value' => '198.51.100.7' ), $export['data'][0]['data'][0] );
		$this->assertTrue( $export['done'] );

		$erase = \WPConversionHub\Support\Privacy::erase( 'buyer@example.com' );
		$this->assertTrue( $erase['items_removed'] );
		$this->assertSame( '', $order->get_meta( '_wpch_visitor' ) );
		$this->assertSame( array(), \WPConversionHub\Support\Privacy::export( 'buyer@example.com' )['data'] );
		$this->assertSame( array(), \WPConversionHub\Support\Privacy::export( 'other@example.com' )['data'], 'another email matches no orders' );
	}

	public function testIdentityIsDroppedOnceEveryDestinationHasBeenSent(): void {
		$this->configure(
			$this->metaServer() + array(
				'webhook' => array(
					'enabled' => true,
					'url'     => 'https://93.184.216.34/hook',
				),
			),
			array( 'consent' => array( 'ads_default' => 'granted' ) )
		);
		$_SERVER['HTTP_USER_AGENT'] = 'BuyerUA';
		$this->dispatcher->dispatch( NormalizedEvent::create( array( 'type' => 'form_submission', 'source' => 'cf7' ) ) );
		$queued = $this->queued();
		$this->assertCount( 2, $queued );
		$id = $queued[0]['event']['event_id'];

		$this->dispatcher->deliver( $queued[0] );
		$this->assertNotSame( array(), \WPConversionHub\Storage\IdentityStore::get( $id ), 'the second destination still needs it' );

		$this->dispatcher->deliver( $queued[1] );
		$this->assertSame( array(), \WPConversionHub\Storage\IdentityStore::get( $id ) );
	}

	public function testUninstallRemovesQueuedActionsCronAndTransients(): void {
		global $wpdb;
		$wpdb->insert_meta( 'wp_wc_orders_meta', '_wpch_visitor' );
		$wpdb->insert_meta( 'wp_wc_orders_meta', '_other' );
		$wpdb->insert_option( '_transient_wpch_cq_abc' );
		$wpdb->insert_option( '_transient_timeout_wpch_cq_abc' );
		$wpdb->insert_option( '_transient_wpch_rl_global' );
		$wpdb->insert_option( 'blogname' );
		$this->assertSame( 4, $wpdb->count_options() );
		$this->assertTrue( $wpdb->table_exists( 'wp_wpch_events' ) );
		$GLOBALS['wpch_test_unscheduled_hooks']        = array();
		$GLOBALS['wpch_test_options']['wpch_settings'] = array( 'x' => 1 );

		define( 'WP_UNINSTALL_PLUGIN', 'conversion-relay/conversion-relay.php' );
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertSame( 1, $wpdb->count_options(), 'only the unrelated option is left' );
		$this->assertFalse( $wpdb->table_exists( 'wp_wpch_events' ) );
		$this->assertSame( 'wpch', $GLOBALS['wpch_test_unscheduled_group'] );
		$this->assertContains( 'wpch_deliver_event', $GLOBALS['wpch_test_unscheduled_hooks'] );
		$this->assertArrayNotHasKey( 'wpch_settings', $GLOBALS['wpch_test_options'] );
		$this->assertContains( 'wpch_notice_dismissed', $GLOBALS['wpch_test_deleted_meta'] );
		$this->assertContains( '_wpch_visitor', $GLOBALS['wpch_test_deleted_meta'] );
		$this->assertSame( 1, $wpdb->count_meta( 'wp_wc_orders_meta' ), 'only the order meta of this plugin is removed' );
	}
}
