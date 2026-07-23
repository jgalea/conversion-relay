<?php

declare( strict_types=1 );

namespace WPConversionHub;

use WPConversionHub\Admin\SettingsPage;
use WPConversionHub\Hub\Dispatcher;
use WPConversionHub\Hub\Queue;
use WPConversionHub\Storage\EventLog;
use WPConversionHub\Storage\Schema;
use WPConversionHub\Support\Registry;
use WPConversionHub\Support\Settings;

final class Plugin {

	private static ?Plugin $instance = null;

	private Registry $registry;
	private Dispatcher $dispatcher;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->registry   = new Registry();
		$this->dispatcher = new Dispatcher( $this->registry );
	}

	public function boot(): void {
		Schema::maybe_upgrade();

		$this->register_builtins();

		do_action( 'wpch_register_source', $this->registry );
		do_action( 'wpch_register_destination', $this->registry );

		$this->register_sources();

		add_action( Queue::HOOK, array( $this->dispatcher, 'deliver' ) );

		( new Frontend( $this->registry, $this->dispatcher ) )->hooks();

		if ( is_admin() ) {
			( new SettingsPage( $this->registry ) )->hooks();
		}

		if ( ! wp_next_scheduled( 'wpch_prune_log' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'wpch_prune_log' );
		}
		add_action( 'wpch_prune_log', static fn() => EventLog::prune( 30 ) );

		do_action( 'wpch_booted', $this->registry, $this->dispatcher );
	}

	private function register_builtins(): void {
		$sources = array(
			Sources\Core::class,
			Sources\WooCommerce::class,
			Sources\Edd::class,
			Sources\GravityForms::class,
			Sources\ContactForm7::class,
			Sources\ElementorForms::class,
			Sources\WPForms::class,
			Sources\FluentForms::class,
			Sources\NinjaForms::class,
			Sources\GiveWP::class,
		);
		foreach ( $sources as $class ) {
			if ( class_exists( $class ) ) {
				$this->registry->add_source( new $class() );
			}
		}

		$destinations = array(
			Destinations\Ga4::class,
			Destinations\Plausible::class,
			Destinations\Fathom::class,
			Destinations\Umami::class,
			Destinations\PostHog::class,
			Destinations\MetaAds::class,
			Destinations\GoogleAds::class,
			Destinations\Vireo::class,
			Destinations\Matomo::class,
			Destinations\Webhook::class,
			Destinations\TikTok::class,
			Destinations\Pinterest::class,
		);
		foreach ( $destinations as $class ) {
			if ( class_exists( $class ) ) {
				$this->registry->add_destination( new $class() );
			}
		}
	}

	private function register_sources(): void {
		foreach ( $this->registry->sources() as $source ) {
			if ( Settings::source_enabled( $source->id() ) && $source->is_available() ) {
				$source->register( $this->dispatcher );
			}
		}
	}

	public function registry(): Registry {
		return $this->registry;
	}

	public function dispatcher(): Dispatcher {
		return $this->dispatcher;
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'wpch_prune_log' );
	}
}
