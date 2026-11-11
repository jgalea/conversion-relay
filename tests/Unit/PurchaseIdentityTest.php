<?php

declare( strict_types=1 );

namespace WPConversionHub\Tests\Unit;

use WPConversionHub\Sources\WooCommerce;

final class FakeOrder {

	/** @var array<string,mixed> */
	public array $meta = array();

	private int $id;
	private float $total;

	public function __construct( int $id, float $total = 20.0 ) {
		$this->id    = $id;
		$this->total = $total;
	}

	public function get_id(): int {
		return $this->id;
	}
	public function get_total(): float {
		return $this->total;
	}
	public function get_amount(): float {
		return $this->total;
	}
	public function get_currency(): string {
		return 'EUR';
	}
	public function get_items(): array {
		return array();
	}
	public function get_billing_email(): string {
		return 'buyer@example.com';
	}
	public function get_billing_phone(): string {
		return '';
	}
	public function get_billing_first_name(): string {
		return '';
	}
	public function get_billing_last_name(): string {
		return '';
	}
	public function get_meta( string $key ) {
		return $this->meta[ $key ] ?? '';
	}
	public function update_meta_data( string $key, $value ): void {
		$this->meta[ $key ] = $value;
	}
	public function delete_meta_data( string $key ): void {
		unset( $this->meta[ $key ] );
	}
	public function save_meta_data(): void {}
}

final class PurchaseIdentityTest extends TestCase {

	private function buyer_checkout( FakeOrder $order ): void {
		$_COOKIE['_fbp']            = 'fb.1.buyer';
		$_COOKIE['_ga']             = 'GA1.1.111.222';
		$_SERVER['REMOTE_ADDR']     = '198.51.100.7';
		$_SERVER['HTTP_USER_AGENT'] = 'BuyerBrowser';
		( new WooCommerce() )->store_visitor( $order );
	}

	private function admin_request(): void {
		$_COOKIE                    = array( '_fbp' => 'fb.1.admin' );
		$_SERVER['REMOTE_ADDR']     = '192.0.2.1';
		$_SERVER['HTTP_USER_AGENT'] = 'AdminBrowser';
	}

	public function testPurchaseUsesTheIdentityStoredAtCheckoutNotTheAdminRequest(): void {
		$order = new FakeOrder( 501 );
		$this->buyer_checkout( $order );
		$GLOBALS['wpch_test_orders'][501] = $order;

		$this->admin_request();
		$source = new WooCommerce();
		$source->register( $this->dispatcher );
		$source->on_paid( 501 );

		$identity = $this->events[0]->identity;
		$this->assertSame( 'fb.1.buyer', $identity['fbp'] );
		$this->assertSame( '198.51.100.7', $identity['ip'] );
		$this->assertSame( 'BuyerBrowser', $identity['user_agent'] );
		$this->assertSame( '111.222', $identity['client_id'] );
	}

	public function testBuyersConsentTravelsWithTheOrder(): void {
		$this->configure( $this->metaServer() );
		$order = new FakeOrder( 502 );
		// The buyer refused marketing at checkout.
		$_COOKIE = array();
		( new WooCommerce() )->store_visitor( $order );
		$GLOBALS['wpch_test_orders'][502] = $order;

		// The admin who completes the order has granted everything: the test hook
		// stands in for an admin's marketing consent cookie.
		wpch_test_filter( 'wpch_consent', static fn( $granted, $category, $event, $reason ) => 'stored' === $reason ? $granted : true );

		$source = new WooCommerce();
		$source->register( $this->dispatcher );
		$source->on_paid( 502 );

		$this->assertSame( array(), $this->queued(), 'ads default is denied, and the order carries that decision' );
	}

	public function testOrderWithNoStoredDecisionIsNotSentToAdvertisers(): void {
		$this->configure( $this->metaServer(), array( 'consent' => array( 'ads_default' => 'granted' ) ) );
		$GLOBALS['wpch_test_orders'][503] = new FakeOrder( 503 );

		$source = new WooCommerce();
		$source->register( $this->dispatcher );
		$source->on_paid( 503 );

		$this->assertSame( array(), $this->queued() );
	}

	public function testOrderWithAGrantedDecisionIsSentWithTheBuyersIdentity(): void {
		$this->configure( $this->metaServer(), array( 'consent' => array( 'ads_default' => 'granted' ) ) );
		$order = new FakeOrder( 504 );
		$this->buyer_checkout( $order );
		$GLOBALS['wpch_test_orders'][504] = $order;

		$this->admin_request();
		$source = new WooCommerce();
		$source->register( $this->dispatcher );
		$source->on_paid( 504 );
		$this->dispatcher->deliver( $this->queued()[0] );

		$user = json_decode( $GLOBALS['wpch_test_http'][0]['args']['body'], true )['data'][0]['user_data'];
		$this->assertSame( 'BuyerBrowser', $user['client_user_agent'] );
		$this->assertSame( '198.51.100.7', $user['client_ip_address'] );
		$this->assertSame( 'fb.1.buyer', $user['fbp'] );
	}

	public function testGa4SessionIdIsReadFromTheContainerCookie(): void {
		$this->configure(
			array(
				'ga4' => array(
					'enabled'        => true,
					'measurement_id' => 'G-ABC123',
					'api_secret'     => 's',
				),
			),
			array( 'consent' => array( 'require_consent' => false ) )
		);
		$_COOKIE['_ga']        = 'GA1.1.111.222';
		$_COOKIE['_ga_ABC123'] = 'GS2.1.s1700000000$o3$g1$t1700000100$j0$l0$h0';

		$order = new FakeOrder( 505 );
		( new WooCommerce() )->store_visitor( $order );
		$GLOBALS['wpch_test_orders'][505] = $order;
		$_COOKIE                          = array();

		$source = new WooCommerce();
		$source->register( $this->dispatcher );
		$source->on_paid( 505 );
		$this->dispatcher->deliver( $this->queued()[0] );

		$body  = json_decode( $GLOBALS['wpch_test_http'][0]['args']['body'], true );
		$param = $body['events'][0]['params'];
		$this->assertSame( '111.222', $body['client_id'] );
		$this->assertSame( '1700000000', $param['session_id'] );
		$this->assertSame( 3, $param['session_number'] );
	}

	public function testEachRefundIsItsOwnEventWithItsOwnAmount(): void {
		$GLOBALS['wpch_test_orders'] = array(
			600 => new FakeOrder( 600, 100.0 ),
			601 => new FakeOrder( 601, 10.0 ),
			602 => new FakeOrder( 602, 15.0 ),
		);
		$source                      = new WooCommerce();
		$source->register( $this->dispatcher );
		$source->on_refund( 600, 601 );
		$source->on_refund( 600, 602 );

		$this->assertCount( 2, $this->events );
		$this->assertNotSame( $this->events[0]->event_id, $this->events[1]->event_id );
		$this->assertSame( 10.0, $this->events[0]->value );
		$this->assertSame( 15.0, $this->events[1]->value );
	}

	public function testBeginCheckoutReloadIsOneEvent(): void {
		$GLOBALS['wpch_test_checkout'] = true;
		$GLOBALS['wpch_test_wc']       = (object) array(
			'cart' => new class() {
				public function get_total( string $ctx ): string {
					return '20';
				}
				public function get_cart_hash(): string {
					return 'abc';
				}
			},
		);
		$source                        = new WooCommerce();
		$source->register( $this->dispatcher );
		$source->maybe_begin_checkout();
		$source->maybe_begin_checkout();

		$this->assertCount( 1, $this->events );
	}

	public function testOrderWithNoDecisionIsSentWithoutIdentityWhenConsentGatingIsOff(): void {
		$this->configure( $this->metaServer(), array( 'consent' => array( 'require_consent' => false ) ) );
		$GLOBALS['wpch_test_orders'][506] = new FakeOrder( 506 );
		$this->admin_request();

		$source = new WooCommerce();
		$source->register( $this->dispatcher );
		$source->on_paid( 506 );
		$this->dispatcher->deliver( $this->queued()[0] );

		$user = json_decode( $GLOBALS['wpch_test_http'][0]['args']['body'], true )['data'][0]['user_data'];
		$this->assertArrayNotHasKey( 'client_user_agent', $user, 'the admin request must not stand in for the buyer' );
		$this->assertArrayNotHasKey( 'fbp', $user );
		$this->assertArrayNotHasKey( 'client_ip_address', $user );
	}

	public function testRenewalInheritsIdentityAndConsentFromTheInitialOrder(): void {
		$this->configure( $this->metaServer(), array( 'consent' => array( 'ads_default' => 'granted' ) ) );
		$initial = new FakeOrder( 507 );
		$this->buyer_checkout( $initial );
		$renewal = new FakeOrder( 508 );
		$GLOBALS['wpch_test_orders'][508] = $renewal;
		$GLOBALS['wpch_test_subscription_parent'] = $initial;
		$this->admin_request();

		$source = new WooCommerce();
		$source->register( $this->dispatcher );
		$source->on_paid( 508 );
		$this->dispatcher->deliver( $this->queued()[0] );

		$user = json_decode( $GLOBALS['wpch_test_http'][0]['args']['body'], true )['data'][0]['user_data'];
		$this->assertSame( 'BuyerBrowser', $user['client_user_agent'] );
		$this->assertSame( 'fb.1.buyer', $user['fbp'] );
	}
}
