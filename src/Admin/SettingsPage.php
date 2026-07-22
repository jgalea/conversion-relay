<?php

declare( strict_types=1 );

namespace WPConversionHub\Admin;

use WPConversionHub\Storage\EventLog;
use WPConversionHub\Support\Registry;
use WPConversionHub\Support\Settings;

/**
 * Settings API admin screen: Destinations, Sources, Consent, Status. Secret
 * fields are masked and capability-guarded; nothing here prints a stored secret.
 */
final class SettingsPage {

	private const SLUG = 'wp-conversion-hub';
	private const CAP  = 'manage_options';

	private Registry $registry;

	public function __construct( Registry $registry ) {
		$this->registry = $registry;
	}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_wpch_save', array( $this, 'save' ) );
	}

	public function menu(): void {
		add_options_page(
			__( 'WP Conversion Hub', 'wp-conversion-hub' ),
			__( 'Conversion Hub', 'wp-conversion-hub' ),
			self::CAP,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'destinations'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs = array(
			'destinations' => __( 'Destinations', 'wp-conversion-hub' ),
			'sources'      => __( 'Sources', 'wp-conversion-hub' ),
			'consent'      => __( 'Consent', 'wp-conversion-hub' ),
			'status'       => __( 'Status', 'wp-conversion-hub' ),
		);

		echo '<div class="wrap"><h1>' . esc_html__( 'WP Conversion Hub', 'wp-conversion-hub' ) . '</h1>';
		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wp-conversion-hub' ) . '</p></div>';
		}

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			printf(
				'<a href="%s" class="nav-tab %s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=' . self::SLUG . '&tab=' . $key ) ),
				$key === $tab ? 'nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</h2>';

		if ( 'status' === $tab ) {
			$this->render_status();
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'wpch_save' );
			echo '<input type="hidden" name="action" value="wpch_save" />';
			echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />';
			if ( 'destinations' === $tab ) {
				$this->render_destinations();
			} elseif ( 'sources' === $tab ) {
				$this->render_sources();
			} elseif ( 'consent' === $tab ) {
				$this->render_consent();
			}
			submit_button();
			echo '</form>';
		}
		echo '</div>';
	}

	private function render_destinations(): void {
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $this->registry->destinations() as $dest ) {
			$id  = $dest->id();
			$cfg = Settings::dest_config( $id );
			echo '<tr><th colspan="2"><h3>' . esc_html( $dest->label() ) . '</h3></th></tr>';
			$enabled_label = sprintf(
				/* translators: %s: destination name, e.g. "Google Analytics 4". */
				__( 'Send conversions to %s', 'wp-conversion-hub' ),
				$dest->label()
			);
			printf(
				'<tr><th scope="row">%s</th><td><label><input type="checkbox" name="dest[%s][enabled]" value="1" %s /> %s</label></td></tr>',
				esc_html__( 'Enabled', 'wp-conversion-hub' ),
				esc_attr( $id ),
				checked( ! empty( $cfg['enabled'] ), true, false ),
				esc_html( $enabled_label )
			);
			foreach ( $dest->settings_fields() as $key => $field ) {
				$is_secret = ! empty( $field['secret'] );
				$value     = (string) ( $cfg[ $key ] ?? '' );
				$display   = $is_secret && '' !== $value ? '' : $value;
				$ph        = $is_secret && '' !== $value ? __( '••••••••  (saved — leave blank to keep)', 'wp-conversion-hub' ) : '';
				printf(
					'<tr><th scope="row">%s</th><td><input type="text" class="regular-text" name="dest[%s][%s]" value="%s" placeholder="%s" autocomplete="off" /></td></tr>',
					esc_html( (string) $field['label'] ),
					esc_attr( $id ),
					esc_attr( $key ),
					esc_attr( $display ),
					esc_attr( $ph )
				);
			}
		}
		echo '</tbody></table>';
	}

	private function render_sources(): void {
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $this->registry->sources() as $source ) {
			$id      = $source->id();
			$all     = Settings::all();
			$scfg    = $all['sources'][ $id ] ?? array();
			$channel = Settings::source_channel( $id );
			$avail   = $source->is_available() ? '' : ' <em>' . esc_html__( '(plugin not active)', 'wp-conversion-hub' ) . '</em>';
			echo '<tr><th scope="row">' . esc_html( $source->label() ) . wp_kses_post( $avail ) . '</th><td>';
			printf(
				'<label><input type="checkbox" name="src[%s][enabled]" value="1" %s /> %s</label> &nbsp; ',
				esc_attr( $id ),
				checked( ! empty( $scfg['enabled'] ), true, false ),
				esc_html__( 'Track', 'wp-conversion-hub' )
			);
			echo esc_html__( 'Channel:', 'wp-conversion-hub' ) . ' <select name="src[' . esc_attr( $id ) . '][channel]">';
			foreach ( array(
				'both'        => __( 'Server + client', 'wp-conversion-hub' ),
				'server_only' => __( 'Server only', 'wp-conversion-hub' ),
				'client_only' => __( 'Client only', 'wp-conversion-hub' ),
			) as $val => $label ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $channel, $val, false ), esc_html( $label ) );
			}
			echo '</select></td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_consent(): void {
		$c = Settings::consent();
		echo '<table class="form-table" role="presentation"><tbody>';
		printf(
			'<tr><th scope="row">%s</th><td><label><input type="checkbox" name="consent[require_consent]" value="1" %s /> %s</label></td></tr>',
			esc_html__( 'Require consent', 'wp-conversion-hub' ),
			checked( ! empty( $c['require_consent'] ), true, false ),
			esc_html__( 'Gate destinations on consent', 'wp-conversion-hub' )
		);
		printf(
			'<tr><th scope="row">%s</th><td><label><input type="checkbox" name="consent[respect_dnt]" value="1" %s /> %s</label></td></tr>',
			esc_html__( 'Respect DNT', 'wp-conversion-hub' ),
			checked( ! empty( $c['respect_dnt'] ), true, false ),
			esc_html__( 'Honor the browser Do Not Track header', 'wp-conversion-hub' )
		);
		foreach ( array(
			'analytics_default' => __( 'Analytics default', 'wp-conversion-hub' ),
			'ads_default'       => __( 'Advertising default', 'wp-conversion-hub' ),
		) as $key => $label ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><select name="consent[' . esc_attr( $key ) . ']">';
			foreach ( array(
				'granted' => __( 'Granted', 'wp-conversion-hub' ),
				'denied'  => __( 'Denied', 'wp-conversion-hub' ),
			) as $val => $vlabel ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( (string) ( $c[ $key ] ?? '' ), $val, false ), esc_html( $vlabel ) );
			}
			echo '</select></td></tr>';
		}
		printf(
			'<tr><th scope="row">%s</th><td><label><input type="checkbox" name="enhanced_conversions" value="1" %s /> %s</label></td></tr>',
			esc_html__( 'Enhanced conversions', 'wp-conversion-hub' ),
			checked( Settings::enhanced_conversions_enabled(), true, false ),
			esc_html__( 'Hash and send first-party customer data (opt-in)', 'wp-conversion-hub' )
		);
		echo '</tbody></table>';
	}

	private function render_status(): void {
		echo '<p>' . esc_html__( 'Most recent delivery attempts.', 'wp-conversion-hub' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr>'
			. '<th>' . esc_html__( 'Time', 'wp-conversion-hub' ) . '</th>'
			. '<th>' . esc_html__( 'Type', 'wp-conversion-hub' ) . '</th>'
			. '<th>' . esc_html__( 'Source', 'wp-conversion-hub' ) . '</th>'
			. '<th>' . esc_html__( 'Destination', 'wp-conversion-hub' ) . '</th>'
			. '<th>' . esc_html__( 'Status', 'wp-conversion-hub' ) . '</th>'
			. '<th>' . esc_html__( 'Attempts', 'wp-conversion-hub' ) . '</th>'
			. '<th>' . esc_html__( 'Response', 'wp-conversion-hub' ) . '</th>'
			. '</tr></thead><tbody>';
		$rows = EventLog::recent( 50 );
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No events yet.', 'wp-conversion-hub' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( (string) ( $row['created_at'] ?? '' ) ),
				esc_html( (string) ( $row['type'] ?? '' ) ),
				esc_html( (string) ( $row['source'] ?? '' ) ),
				esc_html( (string) ( $row['destination'] ?? '' ) ),
				esc_html( (string) ( $row['status'] ?? '' ) ),
				esc_html( (string) ( $row['attempts'] ?? '' ) ),
				esc_html( mb_substr( (string) ( $row['response'] ?? '' ), 0, 80 ) )
			);
		}
		echo '</tbody></table>';
	}

	public function save(): void {
		if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'wpch_save' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'wp-conversion-hub' ), '', array( 'response' => 403 ) );
		}

		$tab      = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
		$settings = Settings::all();
		$settings = array_merge( Settings::defaults(), $settings );

		if ( 'destinations' === $tab && isset( $_POST['dest'] ) && is_array( $_POST['dest'] ) ) {
			$posted = wp_unslash( $_POST['dest'] ); // phpcs:ignore WordPress.Security.ValidationSanitization
			foreach ( $this->registry->destinations() as $dest ) {
				$id                  = $dest->id();
				$in                  = isset( $posted[ $id ] ) && is_array( $posted[ $id ] ) ? $posted[ $id ] : array();
				$existing            = $settings['destinations'][ $id ] ?? array();
				$existing['enabled'] = ! empty( $in['enabled'] );
				foreach ( $dest->settings_fields() as $key => $field ) {
					$val = isset( $in[ $key ] ) ? sanitize_text_field( (string) $in[ $key ] ) : '';
					if ( ! empty( $field['secret'] ) && '' === $val ) {
						continue; // keep saved secret when field left blank
					}
					$existing[ $key ] = $val;
				}
				$settings['destinations'][ $id ] = $existing;
			}
		}

		if ( 'sources' === $tab && isset( $_POST['src'] ) && is_array( $_POST['src'] ) ) {
			$posted = wp_unslash( $_POST['src'] ); // phpcs:ignore WordPress.Security.ValidationSanitization
			foreach ( $this->registry->sources() as $source ) {
				$id                         = $source->id();
				$in                         = isset( $posted[ $id ] ) && is_array( $posted[ $id ] ) ? $posted[ $id ] : array();
				$settings['sources'][ $id ] = array(
					'enabled' => ! empty( $in['enabled'] ),
					'channel' => in_array( $in['channel'] ?? 'both', array( 'both', 'server_only', 'client_only' ), true ) ? $in['channel'] : 'both',
				);
			}
		}

		if ( 'consent' === $tab ) {
			$consent                          = isset( $_POST['consent'] ) && is_array( $_POST['consent'] ) ? wp_unslash( $_POST['consent'] ) : array();
			$settings['consent']              = array(
				'require_consent'   => ! empty( $consent['require_consent'] ),
				'respect_dnt'       => ! empty( $consent['respect_dnt'] ),
				'analytics_default' => 'denied' === ( $consent['analytics_default'] ?? 'granted' ) ? 'denied' : 'granted',
				'ads_default'       => 'granted' === ( $consent['ads_default'] ?? 'denied' ) ? 'granted' : 'denied',
			);
			$settings['enhanced_conversions'] = ! empty( $_POST['enhanced_conversions'] );
		}

		Settings::save( $settings );

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::SLUG . '&tab=' . $tab . '&updated=1' ) );
		exit;
	}
}
