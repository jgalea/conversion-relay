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

	public function testUninstallRemovesQueuedActionsCronAndTransients(): void {
		global $wpdb;
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
	}
}
