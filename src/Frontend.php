<?php

declare( strict_types=1 );

namespace WPConversionHub;

use WPConversionHub\Destinations\DestinationInterface;
use WPConversionHub\Hub\ClientQueue;
use WPConversionHub\Hub\Dispatcher;
use WPConversionHub\Support\Registry;
use WPConversionHub\Support\Settings;

/**
 * Owns the browser side: enqueues the bridge, hands it the enabled client
 * destinations plus the redirect-safe queue of pending pixel events, and exposes
 * a REST endpoint for client-origin events (scroll, time on page, play).
 */
final class Frontend {

	private Registry $registry;
	private Dispatcher $dispatcher;

	public function __construct( Registry $registry, Dispatcher $dispatcher ) {
		$this->registry   = $registry;
		$this->dispatcher = $dispatcher;
	}

	public function hooks(): void {
		add_action( 'init', array( ClientQueue::class, 'ensure_cookie' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'print_data' ), 5 );
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
	}

	public function enqueue(): void {
		if ( is_admin() ) {
			return;
		}
		wp_register_script(
			'wpch-bridge',
			WPCH_URL . 'assets/js/bridge.js',
			array(),
			WPCH_VERSION,
			true
		);
		wp_enqueue_script( 'wpch-bridge' );
	}

	public function print_data(): void {
		$configs = array();
		foreach ( $this->registry->destinations() as $destination ) {
			if ( ! Settings::dest_enabled( $destination->id() ) || ! $destination->is_configured() ) {
				continue;
			}
			if ( ! in_array( DestinationInterface::TRANSPORT_CLIENT, $destination->transports(), true ) ) {
				continue;
			}
			$configs[ $destination->id() ] = $destination->client_config();
		}

		$forms = array();
		foreach ( $this->registry->sources() as $source ) {
			if ( ! Settings::source_enabled( $source->id() ) || ! $source->is_available() ) {
				continue;
			}
			foreach ( $source->client_selectors() as $selector ) {
				$forms[] = $selector;
			}
		}
		$forms = array_values( array_unique( $forms ) );

		$events = ClientQueue::flush();

		if ( empty( $configs ) && empty( $events ) && empty( $forms ) ) {
			return;
		}

		$data = array(
			'restUrl'      => esc_url_raw( rest_url( 'wpch/v1/event' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'destinations' => $configs,
			'events'       => array_values( $events ),
			'forms'        => $forms,
		);

		wp_print_inline_script_tag(
			'window.wpchData = ' . wp_json_encode( $data ) . ';',
			array( 'id' => 'wpch-data' )
		);
	}

	public function register_rest(): void {
		register_rest_route(
			'wpch/v1',
			'/event',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'rest_permission' ),
				'callback'            => array( $this, 'rest_event' ),
			)
		);
	}

	/**
	 * The public endpoint is CSRF-guarded by the front-end REST nonce. This is not
	 * authentication (see rest_event for the real abuse controls); it only proves
	 * the request came from a page this site rendered.
	 *
	 * @param \WP_REST_Request $request
	 */
	public function rest_permission( $request ): bool {
		return false !== wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' );
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function rest_event( $request ) {
		if ( ! $this->rest_rate_ok() ) {
			return new \WP_REST_Response( array( 'ok' => false ), 429 );
		}

		$type = sanitize_key( (string) $request->get_param( 'type' ) );

		// The public endpoint only accepts genuinely client-origin events. Money
		// events (purchase, donation, refund, ...) must come from a server-side
		// source, never an anonymous browser POST, so they cannot be fabricated.
		$allowed = apply_filters( 'wpch_rest_allowed_types', \WPConversionHub\Event\EventType::client_origin() );
		if ( ! is_array( $allowed ) || ! in_array( $type, $allowed, true ) ) {
			return new \WP_REST_Response( array( 'ok' => false ), 400 );
		}

		$value = $request->get_param( 'value' );
		$value = is_numeric( $value ) ? (float) $value : null;

		$meta = $request->get_param( 'meta' );
		$meta = is_array( $meta ) ? $meta : array();
		if ( count( $meta ) > 20 || strlen( (string) wp_json_encode( $meta ) ) > 2000 ) {
			$meta = array();
		}
		$meta = map_deep( $meta, 'sanitize_text_field' );

		$this->dispatcher->dispatch(
			\WPConversionHub\Event\NormalizedEvent::create(
				array(
					'type'   => $type,
					'source' => 'client',
					'origin' => 'client',
					'value'  => $value,
					'meta'   => $meta,
					'url'    => esc_url_raw( (string) $request->get_param( 'url' ) ),
				)
			)
		);

		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	private function rest_rate_ok(): bool {
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'anon';
		$key  = 'wpch_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= 60 ) {
			return false;
		}
		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );
		return true;
	}
}
