<?php

declare( strict_types=1 );

namespace WPConversionHub\Admin;

use WPConversionHub\Destinations\DestinationInterface;
use WPConversionHub\Storage\EventLog;
use WPConversionHub\Support\License;
use WPConversionHub\Support\Registry;
use WPConversionHub\Support\Settings;

/**
 * Settings API admin screen: Destinations, Sources, Consent, Status. Secret
 * fields are masked and capability-guarded; nothing here prints a stored secret.
 */
final class SettingsPage {

	private const SLUG = 'conversion-relay';
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
			__( 'Conversion Relay', 'conversion-relay' ),
			__( 'Conversion Relay', 'conversion-relay' ),
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
			'destinations' => __( 'Destinations', 'conversion-relay' ),
			'sources'      => __( 'Sources', 'conversion-relay' ),
			'consent'      => __( 'Consent', 'conversion-relay' ),
			'status'       => __( 'Status', 'conversion-relay' ),
		);

		echo '<div class="wrap"><h1>Conversion Relay</h1>';
		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'conversion-relay' ) . '</p></div>';
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
			$id     = $dest->id();
			$cfg    = Settings::dest_config( $id );
			$is_pro = DestinationInterface::TIER_PRO === $dest->tier();
			$locked = $is_pro && ! License::is_pro();

			$heading = esc_html( $dest->label() );
			if ( $is_pro ) {
				$heading .= ' <span class="wpch-pro-badge" style="font-size:11px;background:#0e8f82;color:#fff;padding:2px 7px;border-radius:3px;vertical-align:middle;">' . esc_html__( 'PRO', 'conversion-relay' ) . '</span>';
			}
			echo '<tr><th colspan="2"><h3>' . wp_kses_post( $heading ) . '</h3></th></tr>';

			if ( $locked ) {
				printf(
					'<tr><td colspan="2"><p style="margin:0;color:#555;">%s</p><p style="margin:4px 0 0;"><a href="%s" target="_blank" rel="noopener">%s</a></p></td></tr>',
					esc_html( $dest->pro_note() ),
					esc_url( 'https://github.com/jgalea/conversion-relay' ),
					esc_html__( 'Upgrade to unlock', 'conversion-relay' )
				);
				continue;
			}

			printf(
				/* translators: %s: destination name. */
				'<tr><th scope="row">%s</th><td><label><input type="checkbox" name="dest[%s][enabled]" value="1" %s /> %s</label></td></tr>',
				esc_html__( 'Enabled', 'conversion-relay' ),
				esc_attr( $id ),
				checked( ! empty( $cfg['enabled'] ), true, false ),
				esc_html( sprintf( /* translators: %s: destination name. */ __( 'Send conversions to %s', 'conversion-relay' ), $dest->label() ) )
			);
			foreach ( $dest->settings_fields() as $key => $field ) {
				$is_secret = ! empty( $field['secret'] );
				$value     = (string) ( $cfg[ $key ] ?? '' );
				$display   = $is_secret && '' !== $value ? '' : $value;
				$ph        = $is_secret && '' !== $value ? esc_html__( 'saved — leave blank to keep', 'conversion-relay' ) : '';
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
		$channels = array(
			'both'        => __( 'Server + client', 'conversion-relay' ),
			'server_only' => __( 'Server only', 'conversion-relay' ),
			'client_only' => __( 'Client only', 'conversion-relay' ),
		);
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $this->registry->sources() as $source ) {
			$id      = $source->id();
			$all     = Settings::all();
			$scfg    = $all['sources'][ $id ] ?? array();
			$channel = Settings::source_channel( $id );
			$avail   = $source->is_available() ? '' : ' <em>' . esc_html__( '(plugin not active)', 'conversion-relay' ) . '</em>';
			echo '<tr><th scope="row">' . esc_html( $source->label() ) . wp_kses_post( $avail ) . '</th><td>';
			printf(
				'<label><input type="checkbox" name="src[%s][enabled]" value="1" %s /> %s</label> &nbsp; ',
				esc_attr( $id ),
				checked( ! empty( $scfg['enabled'] ), true, false ),
				esc_html__( 'Track', 'conversion-relay' )
			);
			echo esc_html__( 'Channel:', 'conversion-relay' ) . ' <select name="src[' . esc_attr( $id ) . '][channel]">';
			foreach ( $channels as $val => $label ) {
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
			esc_html__( 'Require consent', 'conversion-relay' ),
			checked( ! empty( $c['require_consent'] ), true, false ),
			esc_html__( 'Gate destinations on consent', 'conversion-relay' )
		);
		printf(
			'<tr><th scope="row">%s</th><td><label><input type="checkbox" name="consent[respect_dnt]" value="1" %s /> %s</label></td></tr>',
			esc_html__( 'Respect DNT', 'conversion-relay' ),
			checked( ! empty( $c['respect_dnt'] ), true, false ),
			esc_html__( 'Honor the browser Do Not Track header', 'conversion-relay' )
		);
		$defaults = array(
			'analytics_default' => __( 'Analytics default', 'conversion-relay' ),
			'ads_default'       => __( 'Advertising default', 'conversion-relay' ),
		);
		$choices  = array(
			'granted' => __( 'Granted', 'conversion-relay' ),
			'denied'  => __( 'Denied', 'conversion-relay' ),
		);
		foreach ( $defaults as $key => $label ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><select name="consent[' . esc_attr( $key ) . ']">';
			foreach ( $choices as $val => $vlabel ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( (string) ( $c[ $key ] ?? '' ), $val, false ), esc_html( $vlabel ) );
			}
			echo '</select></td></tr>';
		}
		printf(
			'<tr><th scope="row">%s</th><td><label><input type="checkbox" name="enhanced_conversions" value="1" %s /> %s</label></td></tr>',
			esc_html__( 'Enhanced conversions', 'conversion-relay' ),
			checked( Settings::enhanced_conversions_enabled(), true, false ),
			esc_html__( 'Hash and send first-party customer data (opt-in)', 'conversion-relay' )
		);
		echo '</tbody></table>';
	}

	private function render_status(): void {
		echo '<p>' . esc_html__( 'Most recent delivery attempts.', 'conversion-relay' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array(
			__( 'Time', 'conversion-relay' ),
			__( 'Type', 'conversion-relay' ),
			__( 'Source', 'conversion-relay' ),
			__( 'Destination', 'conversion-relay' ),
			__( 'Status', 'conversion-relay' ),
			__( 'Attempts', 'conversion-relay' ),
			__( 'Response', 'conversion-relay' ),
		) as $header ) {
			echo '<th>' . esc_html( $header ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		$rows = EventLog::recent( 50 );
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No events yet.', 'conversion-relay' ) . '</td></tr>';
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
			wp_die( esc_html__( 'Unauthorized', 'conversion-relay' ), '', array( 'response' => 403 ) );
		}

		$tab      = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
		$settings = Settings::all();
		$settings = array_merge( Settings::defaults(), $settings );

		if ( 'destinations' === $tab && isset( $_POST['dest'] ) && is_array( $_POST['dest'] ) ) {
			$posted = map_deep( wp_unslash( $_POST['dest'] ), 'sanitize_text_field' );
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
			$posted = map_deep( wp_unslash( $_POST['src'] ), 'sanitize_text_field' );
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
			$consent                          = isset( $_POST['consent'] ) && is_array( $_POST['consent'] ) ? map_deep( wp_unslash( $_POST['consent'] ), 'sanitize_text_field' ) : array();
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
