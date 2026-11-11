<?php

declare( strict_types=1 );

namespace WPConversionHub;

use WPConversionHub\Destinations\DestinationInterface;
use WPConversionHub\Hub\ClientQueue;
use WPConversionHub\Hub\Dispatcher;
use WPConversionHub\Hub\RateLimit;
use WPConversionHub\Support\Registry;
use WPConversionHub\Support\Settings;

/**
 * Owns the browser side: enqueues the bridge, hands it the enabled client
 * destinations plus the redirect-safe queue of pending pixel events, and exposes
 * a REST endpoint for client-origin events (play, click).
 */
final class Frontend {

	private Registry $registry;
	private Dispatcher $dispatcher;

	public function __construct( Registry $registry, Dispatcher $dispatcher ) {
		$this->registry   = $registry;
		$this->dispatcher = $dispatcher;
	}

	public function hooks(): void {
		add_action( 'template_redirect', array( $this, 'keep_out_of_page_cache' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_action( 'wp_head', array( $this, 'print_consent_default' ), 1 );
		add_action( 'wp_footer', array( $this, 'print_data' ), 5 );
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
	}

	public function keep_out_of_page_cache(): void {
		if ( ! ClientQueue::has_pending() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
	}

	public function enqueue(): void {
		if ( is_admin() ) {
			return;
		}
		wp_register_script(
			'wpch-bridge',
			WPCH_URL . 'assets/js/bridge.js',
			wp_script_is( 'wp-consent-api', 'registered' ) ? array( 'wp-consent-api' ) : array(),
			WPCH_VERSION,
			true
		);
		wp_enqueue_script( 'wpch-bridge' );
	}

	/**
	 * Consent Mode v2 starts with everything denied, ahead of any gtag config on
	 * the page, including one a theme or another plugin prints in the head. The
	 * bridge then updates it to the visitor's actual state.
	 */
	public function print_consent_default(): void {
		if ( is_admin() || ! $this->uses_consent_mode() ) {
			return;
		}
		wp_print_inline_script_tag(
			'window.dataLayer=window.dataLayer||[];if(!window.gtag){window.wpchGtagStub=true;window.gtag=function(){window.dataLayer.push(arguments);};}'
			. "window.gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',wait_for_update:500});",
			array( 'id' => 'wpch-consent-default' )
		);
	}

	private function uses_consent_mode(): bool {
		if ( empty( Settings::consent()['require_consent'] ) ) {
			return false;
		}
		foreach ( array( 'ga4', 'google_ads' ) as $id ) {
			if ( Settings::dest_enabled( $id ) ) {
				return true;
			}
		}
		return false;
	}

	public function print_data(): void {
		$configs = array();
		$rules   = array();
		foreach ( $this->registry->destinations() as $destination ) {
			if ( ! Settings::dest_enabled( $destination->id() ) || ! $destination->is_configured() ) {
				continue;
			}
			if ( ! in_array( DestinationInterface::TRANSPORT_CLIENT, $destination->transports(), true ) ) {
				continue;
			}
			$configs[ $destination->id() ] = $destination->client_config();
			$rules[ $destination->id() ]   = array(
				'category' => $destination->consent_category(),
				'events'   => Settings::dest_events( $destination->id() ),
			);
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

		$media = Settings::source_enabled( 'media' );

		$events = ClientQueue::flush();

		if ( empty( $configs ) && empty( $events ) && empty( $forms ) && ! $media ) {
			return;
		}

		$consent = Settings::consent();
		$levels  = array(
			'ads'       => 'granted' === ( $consent['ads_default'] ?? 'denied' ),
			'analytics' => 'granted' === ( $consent['analytics_default'] ?? 'granted' ),
		);
		$signals = ! empty( $consent['require_consent'] ) && ( isset( $configs['ga4'] ) || isset( $configs['google_ads'] ) ) ? $levels : null;
		$gate    = ! empty( $consent['require_consent'] ) ? $levels + array( 'dnt' => ! empty( $consent['respect_dnt'] ) ) : null;

		$data = array(
			'consent'      => $signals,
			'gate'         => $gate,
			'rules'        => $rules,
			'restUrl'      => esc_url_raw( rest_url( 'wpch/v1/event' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'destinations' => $configs,
			'events'       => array_values( $events ),
			'forms'        => $forms,
			'media'        => $media,
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

		// A cached page keeps the nonce it was rendered with, which expires after a
		// day. The bridge fetches a fresh one here when a post comes back 403.
		register_rest_route(
			'wpch/v1',
			'/nonce',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static function () {
					$response = new \WP_REST_Response( array( 'nonce' => wp_create_nonce( 'wp_rest' ) ), 200 );
					$response->header( 'Cache-Control', 'no-store' );
					return $response;
				},
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
		// Answer 200 when throttled so a caller cannot use the status code to
		// probe where the limit sits. Nothing is dispatched either way.
		if ( ! RateLimit::ok() ) {
			return new \WP_REST_Response( array( 'ok' => true ), 200 );
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
		if ( count( $meta ) > 20 || false === wp_json_encode( $meta, 0, 3 ) || strlen( (string) wp_json_encode( $meta ) ) > 2000 ) {
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
}
