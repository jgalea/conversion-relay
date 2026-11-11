<?php

declare( strict_types=1 );

namespace WPConversionHub\Admin;

use WPConversionHub\Destinations\DestinationInterface;
use WPConversionHub\Event\EventType;
use WPConversionHub\Event\NormalizedEvent;
use WPConversionHub\Hub\Queue;
use WPConversionHub\Storage\EventLog;
use WPConversionHub\Support\Identity;
use WPConversionHub\Support\Registry;
use WPConversionHub\Support\Settings;

/**
 * Settings API admin screen: Destinations, Sources, Consent, Status. Secret
 * fields are masked and capability-guarded; nothing here prints a stored secret.
 */
final class SettingsPage {

	private const SLUG = 'conversion-relay';
	private const CAP  = 'manage_options';

	private const DISMISSED_META = 'wpch_notice_dismissed';

	private Registry $registry;

	public function __construct( Registry $registry ) {
		$this->registry = $registry;
	}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_wpch_save', array( $this, 'save' ) );
		add_action( 'admin_post_wpch_status', array( $this, 'status_action' ) );
		add_action( 'admin_post_wpch_dismiss', array( $this, 'dismiss_notice' ) );
		add_action( 'admin_notices', array( $this, 'inactive_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( string $hook ): void {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wpch-admin', WPCH_URL . 'assets/css/admin.css', array(), WPCH_VERSION );
	}

	public function dismiss_notice(): void {
		if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'wpch_dismiss' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'conversion-relay' ), '', array( 'response' => 403 ) );
		}
		update_user_meta( get_current_user_id(), self::DISMISSED_META, 1 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * An unconfigured install sends nothing and looks identical to a working one,
	 * so say so rather than failing silently.
	 */
	public function inactive_notice(): void {
		if ( ! current_user_can( self::CAP ) || get_user_meta( get_current_user_id(), self::DISMISSED_META, true ) ) {
			return;
		}

		// Redundant on our own screen, where the empty state is already visible.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::SLUG === $page ) {
			return;
		}

		$has_destination = false;
		foreach ( $this->registry->destinations() as $destination ) {
			if ( Settings::dest_enabled( $destination->id() ) && $destination->is_configured() ) {
				$has_destination = true;
				break;
			}
		}

		$has_source = false;
		foreach ( $this->registry->sources() as $source ) {
			if ( Settings::source_enabled( $source->id() ) ) {
				$has_source = true;
				break;
			}
		}

		if ( $has_destination && $has_source ) {
			return;
		}

		if ( ! $has_destination ) {
			$message = __( 'Conversion Relay is active but no destination is enabled, so no conversions are being sent anywhere.', 'conversion-relay' );
		} else {
			$message = __( 'Conversion Relay has a destination enabled but no sources, so there are no conversions to send.', 'conversion-relay' );
		}

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a> &nbsp; <a href="%s">%s</a></p></div>',
			esc_html( $message ),
			esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ),
			esc_html__( 'Open settings', 'conversion-relay' ),
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpch_dismiss' ), 'wpch_dismiss' ) ),
			esc_html__( 'Dismiss', 'conversion-relay' )
		);
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

		$this->render_flash();

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
			// First submit button in the form, so Enter in a field saves instead of firing a destination's test button.
			echo '<button type="submit" class="screen-reader-text" tabindex="-1" aria-hidden="true">' . esc_html__( 'Save changes', 'conversion-relay' ) . '</button>';
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

	private function render_flash(): void {
		$flash = get_transient( 'wpch_flash_' . get_current_user_id() );
		if ( ! is_array( $flash ) ) {
			return;
		}
		delete_transient( 'wpch_flash_' . get_current_user_id() );

		printf(
			'<div class="notice notice-%s wpch-test-result"><p>%s</p>%s</div>',
			esc_attr( (string) $flash['type'] ),
			esc_html( (string) $flash['message'] ),
			'' !== (string) $flash['detail'] ? '<pre>' . esc_html( (string) $flash['detail'] ) . '</pre>' : ''
		);
	}

	private function render_destinations(): void {
		$on  = array();
		$off = array();
		foreach ( $this->registry->destinations() as $dest ) {
			if ( Settings::dest_enabled( $dest->id() ) ) {
				$on[] = $dest;
			} else {
				$off[] = $dest;
			}
		}
		$all = array_merge( $on, $off );

		echo '<p class="description">' . esc_html__( 'Turn on the platforms that should receive conversions. Enabled destinations are listed first.', 'conversion-relay' ) . '</p>';

		foreach ( $all as $dest ) {
			$id      = $dest->id();
			$cfg     = Settings::dest_config( $id );
			$enabled = ! empty( $cfg['enabled'] );

			if ( ! $enabled ) {
				$chip = array( '', __( 'Off', 'conversion-relay' ) );
			} elseif ( ! $dest->is_configured() ) {
				$chip = array( 'is-warn', __( 'Needs setup', 'conversion-relay' ) );
			} else {
				$chip = array( 'is-on', __( 'Enabled', 'conversion-relay' ) );
			}

			printf(
				'<details class="wpch-dest" %s><summary>%s <span class="wpch-chip %s">%s</span></summary><div class="wpch-dest-body">',
				$enabled ? 'open' : '',
				esc_html( $dest->label() ),
				esc_attr( $chip[0] ),
				esc_html( $chip[1] )
			);

			printf(
				'<p><label><input type="checkbox" id="wpch-%1$s-enabled" name="dest[%1$s][enabled]" value="1" %2$s /> %3$s</label></p>',
				esc_attr( $id ),
				checked( $enabled, true, false ),
				esc_html( sprintf( /* translators: %s: destination name. */ __( 'Send conversions to %s', 'conversion-relay' ), $dest->label() ) )
			);

			foreach ( $dest->settings_fields() as $key => $field ) {
				$this->render_field( $id, (string) $key, $field, $cfg );
			}

			$this->render_events( $id );

			if ( in_array( DestinationInterface::TRANSPORT_SERVER, $dest->transports(), true ) ) {
				printf(
					'<p><button type="submit" class="button" name="wpch_test" value="%s">%s</button> <span class="description">%s</span></p>',
					esc_attr( $id ),
					esc_html__( 'Save and send a test event', 'conversion-relay' ),
					esc_html__( 'Previews the request without sending it, unless the platform has a test mode.', 'conversion-relay' )
				);
			}

			echo '</div></details>';
		}
	}

	/**
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $cfg
	 */
	private function render_field( string $dest_id, string $key, array $field, array $cfg ): void {
		$is_secret = ! empty( $field['secret'] );
		$value     = (string) ( $cfg[ $key ] ?? '' );
		$saved     = $is_secret && '' !== $value;
		$field_id  = 'wpch-' . $dest_id . '-' . $key;
		$name      = 'dest[' . $dest_id . '][' . $key . ']';

		echo '<div class="wpch-field">';
		printf( '<label for="%s">%s</label>', esc_attr( $field_id ), esc_html( (string) $field['label'] ) );
		// API tokens render masked, and out of the browser's autofill history,
		// the same as any other credential field.
		printf(
			'<input type="%s" id="%s" name="%s" value="%s" placeholder="%s" autocomplete="%s" />',
			esc_attr( $is_secret ? 'password' : 'text' ),
			esc_attr( $field_id ),
			esc_attr( $name ),
			esc_attr( $saved ? '' : $value ),
			esc_attr( $saved ? __( 'Saved. Leave blank to keep it.', 'conversion-relay' ) : '' ),
			esc_attr( $is_secret ? 'new-password' : 'off' )
		);
		if ( $saved ) {
			printf(
				'<label><input type="checkbox" name="dest[%s][clear][%s]" value="1" /> %s</label>',
				esc_attr( $dest_id ),
				esc_attr( $key ),
				esc_html__( 'Remove the saved value', 'conversion-relay' )
			);
		}
		if ( isset( $field['help'] ) ) {
			echo '<p class="description">' . esc_html( (string) $field['help'] ) . '</p>';
		}
		echo '</div>';
	}

	private function render_events( string $dest_id ): void {
		printf(
			'<fieldset class="wpch-events"><legend>%s</legend><input type="hidden" name="dest[%s][events_set]" value="1" />',
			esc_html__( 'Send these events', 'conversion-relay' ),
			esc_attr( $dest_id )
		);
		foreach ( EventType::all() as $type ) {
			printf(
				'<label><input type="checkbox" name="dest[%s][events][]" value="%s" %s /> %s</label>',
				esc_attr( $dest_id ),
				esc_attr( $type ),
				checked( Settings::dest_accepts( $dest_id, $type ), true, false ),
				esc_html( ucfirst( str_replace( '_', ' ', $type ) ) )
			);
		}
		echo '</fieldset>';
	}

	private function render_sources(): void {
		$active   = array();
		$inactive = array();
		foreach ( $this->registry->sources() as $source ) {
			if ( $source->is_available() ) {
				$active[] = $source;
			} else {
				$inactive[] = $source;
			}
		}

		$this->render_source_table( $active );

		if ( ! empty( $inactive ) ) {
			printf(
				'<details><summary>%s</summary>',
				esc_html(
					sprintf(
						/* translators: %d: number of sources whose plugin is not active. */
						_n( 'Show %d source whose plugin is not active', 'Show %d sources whose plugins are not active', count( $inactive ), 'conversion-relay' ),
						count( $inactive )
					)
				)
			);
			$this->render_source_table( $inactive );
			echo '</details>';
		}
	}

	/**
	 * @param array<int,\WPConversionHub\Sources\SourceInterface> $sources
	 */
	private function render_source_table( array $sources ): void {
		$channels = array(
			'both'        => __( 'Server + client', 'conversion-relay' ),
			'server_only' => __( 'Server only', 'conversion-relay' ),
			'client_only' => __( 'Client only', 'conversion-relay' ),
		);
		echo '<div class="wpch-table-scroll"><table class="form-table wpch-sources" role="presentation"><tbody>';
		foreach ( $sources as $source ) {
			$id      = $source->id();
			$all     = Settings::all();
			$scfg    = $all['sources'][ $id ] ?? array();
			$channel = Settings::source_channel( $id );
			echo '<tr><th scope="row">' . esc_html( $source->label() ) . '</th><td>';
			printf(
				'<label><input type="checkbox" name="src[%s][enabled]" value="1" %s /> %s</label> &nbsp; ',
				esc_attr( $id ),
				checked( ! empty( $scfg['enabled'] ), true, false ),
				esc_html__( 'Track', 'conversion-relay' )
			);
			printf(
				'<label class="screen-reader-text" for="wpch-src-%1$s-channel">%2$s</label><select id="wpch-src-%1$s-channel" name="src[%1$s][channel]">',
				esc_attr( $id ),
				esc_html( sprintf( /* translators: %s: source name. */ __( 'Channel for %s', 'conversion-relay' ), $source->label() ) )
			);
			foreach ( $channels as $val => $label ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $channel, $val, false ), esc_html( $label ) );
			}
			echo '</select></td></tr>';
		}
		echo '</tbody></table></div>';
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
			'<tr><th scope="row"></th><td><p class="description">%s</p></td></tr>',
			esc_html(
				function_exists( 'wp_has_consent' )
					? __( 'The WP Consent API is active, so each visitor\'s own choice decides. The defaults below apply only before a choice is recorded.', 'conversion-relay' )
					: __( 'No consent plugin is providing a visitor signal, so the defaults below are applied to everyone. Install a WP Consent API compatible banner for per-visitor consent.', 'conversion-relay' )
			)
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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$status      = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$destination = isset( $_GET['destination'] ) ? sanitize_key( wp_unslash( $_GET['destination'] ) ) : '';
		$paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		$per_page = 25;
		$total    = EventLog::count( $status, $destination );
		$rows     = EventLog::page( $status, $destination, $per_page, ( $paged - 1 ) * $per_page );

		$statuses = array(
			EventLog::STATUS_PENDING => __( 'Pending', 'conversion-relay' ),
			EventLog::STATUS_SENT    => __( 'Sent', 'conversion-relay' ),
			EventLog::STATUS_FAILED  => __( 'Failed', 'conversion-relay' ),
			EventLog::STATUS_DEAD    => __( 'Gave up', 'conversion-relay' ),
		);

		echo '<p class="wpch-legend description">' . esc_html__( 'Pending: queued for delivery. Sent: accepted by the platform. Failed: will retry with a growing delay. Gave up: all retries used, re-queue it once the cause is fixed.', 'conversion-relay' ) . '</p>';

		printf( '<form method="get" class="wpch-status-bar"><input type="hidden" name="page" value="%s" /><input type="hidden" name="tab" value="status" />', esc_attr( self::SLUG ) );
		echo '<label class="screen-reader-text" for="wpch-f-status">' . esc_html__( 'Filter by status', 'conversion-relay' ) . '</label><select id="wpch-f-status" name="status"><option value="">' . esc_html__( 'All statuses', 'conversion-relay' ) . '</option>';
		foreach ( $statuses as $value => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $status, $value, false ), esc_html( $label ) );
		}
		echo '</select><label class="screen-reader-text" for="wpch-f-dest">' . esc_html__( 'Filter by destination', 'conversion-relay' ) . '</label><select id="wpch-f-dest" name="destination"><option value="">' . esc_html__( 'All destinations', 'conversion-relay' ) . '</option>';
		foreach ( $this->registry->destinations() as $dest ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $dest->id() ), selected( $destination, $dest->id(), false ), esc_html( $dest->label() ) );
		}
		echo '</select><button type="submit" class="button">' . esc_html__( 'Filter', 'conversion-relay' ) . '</button></form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wpch_status' );
		echo '<input type="hidden" name="action" value="wpch_status" />';
		echo '<p class="wpch-status-bar"><button type="submit" class="button" name="wpch_op" value="retry_all">' . esc_html__( 'Re-queue all failed', 'conversion-relay' ) . '</button>';
		printf(
			'<button type="submit" class="button" name="wpch_op" value="clear" onclick="return confirm(\'%s\');">%s</button></p>',
			esc_js( __( 'Clear the whole delivery log? Pending deliveries are not cancelled.', 'conversion-relay' ) ),
			esc_html__( 'Clear log', 'conversion-relay' )
		);

		echo '<div class="wpch-table-scroll"><table class="widefat striped"><thead><tr>';
		foreach ( array(
			__( 'Time', 'conversion-relay' ),
			__( 'Type', 'conversion-relay' ),
			__( 'Source', 'conversion-relay' ),
			__( 'Destination', 'conversion-relay' ),
			__( 'Status', 'conversion-relay' ),
			__( 'Attempts', 'conversion-relay' ),
			__( 'Response', 'conversion-relay' ),
			__( 'Action', 'conversion-relay' ),
		) as $header ) {
			echo '<th scope="col">' . esc_html( $header ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No events yet.', 'conversion-relay' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$row_status = (string) ( $row['status'] ?? '' );
			$retry      = in_array( $row_status, array( EventLog::STATUS_FAILED, EventLog::STATUS_DEAD ), true )
				? sprintf( '<button type="submit" class="button button-small" name="wpch_op" value="retry:%d">%s</button>', (int) $row['id'], esc_html__( 'Re-queue', 'conversion-relay' ) )
				: '';
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( (string) ( $row['created_at'] ?? '' ) ),
				esc_html( (string) ( $row['type'] ?? '' ) ),
				esc_html( (string) ( $row['source'] ?? '' ) ),
				esc_html( (string) ( $row['destination'] ?? '' ) ),
				esc_html( $statuses[ $row_status ] ?? $row_status ),
				esc_html( (string) ( $row['attempts'] ?? '' ) ),
				esc_html( mb_substr( (string) ( $row['response'] ?? '' ), 0, 80 ) ),
				$retry // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from an int and an escaped label.
			);
		}
		echo '</tbody></table></div></form>';

		$pages = (int) ceil( $total / $per_page );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(
				(string) paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					)
				)
			) . '</div></div>';
		}
	}

	public function status_action(): void {
		if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'wpch_status' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'conversion-relay' ), '', array( 'response' => 403 ) );
		}

		$op = isset( $_POST['wpch_op'] ) ? sanitize_text_field( wp_unslash( $_POST['wpch_op'] ) ) : '';

		if ( 'clear' === $op ) {
			EventLog::clear();
			$this->flash( 'success', __( 'Delivery log cleared.', 'conversion-relay' ) );
		} elseif ( 'retry_all' === $op || 0 === strpos( $op, 'retry:' ) ) {
			$queued = Queue::retry_rows( EventLog::stuck( 'retry_all' === $op ? 0 : absint( substr( $op, 6 ) ) ) );
			$this->flash(
				'success',
				sprintf(
					/* translators: %d: number of deliveries re-queued. */
					_n( 'Re-queued %d delivery.', 'Re-queued %d deliveries.', $queued, 'conversion-relay' ),
					$queued
				)
			);
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::SLUG . '&tab=status' ) );
		exit;
	}

	private function flash( string $type, string $message, string $detail = '' ): void {
		set_transient(
			'wpch_flash_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
				'detail'  => $detail,
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Build the server request for a synthetic purchase. Platforms with a test mode
	 * (Meta with a test event code, GA4's debug endpoint) get the real call; every
	 * other request is captured and shown instead of sent.
	 */
	private function send_test_event( string $dest_id ): void {
		$dest = $this->registry->destination( $dest_id );
		if ( null === $dest ) {
			return;
		}

		$event = NormalizedEvent::create(
			array(
				'type'      => EventType::PURCHASE,
				'source'    => 'test',
				'entity_id' => 'test-' . time(),
				'value'     => 1.0,
				'currency'  => 'USD',
				'url'       => home_url( '/' ),
				'identity'  => Identity::capture(),
				'meta'      => array( 'wpch_debug' => true ),
			)
		);

		$live     = 'ga4' === $dest_id || ( 'meta' === $dest_id && '' !== (string) ( Settings::dest_config( 'meta' )['test_event_code'] ?? '' ) );
		$captured = array();
		$capture  = static function ( $preempt, $args, $url ) use ( &$captured ) {
			$captured = array(
				'url'  => (string) strtok( (string) $url, '?' ),
				'body' => (string) ( $args['body'] ?? '' ),
			);
			return array(
				'headers'  => array(),
				'body'     => '{}',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		if ( ! $live ) {
			add_filter( 'pre_http_request', $capture, 10, 3 );
		}
		$result = $dest->send_server( $event );
		if ( ! $live ) {
			remove_filter( 'pre_http_request', $capture, 10 );
		}

		if ( $live ) {
			$this->flash(
				$result->ok() ? 'success' : 'error',
				sprintf(
					/* translators: %s: destination name. */
					$result->ok() ? __( 'Test event accepted by %s.', 'conversion-relay' ) : __( 'Test event rejected by %s.', 'conversion-relay' ),
					$dest->label()
				),
				mb_substr( $result->message(), 0, 1500 )
			);
			return;
		}

		$this->flash(
			$result->ok() ? 'info' : 'error',
			sprintf(
				/* translators: %s: destination name. */
				__( 'Preview for %s. Nothing was sent.', 'conversion-relay' ),
				$dest->label()
			),
			$result->ok() ? 'POST ' . ( $captured['url'] ?? '' ) . "\n" . mb_substr( (string) ( $captured['body'] ?? '' ), 0, 1500 ) : $result->message()
		);
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
						if ( ! empty( $in['clear'][ $key ] ) ) {
							$existing[ $key ] = '';
						}
						continue; // keep saved secret when field left blank
					}
					$existing[ $key ] = $val;
				}
				if ( ! empty( $in['events_set'] ) ) {
					$existing['events'] = array_values( array_intersect( EventType::all(), isset( $in['events'] ) && is_array( $in['events'] ) ? $in['events'] : array() ) );
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

		$test = isset( $_POST['wpch_test'] ) ? sanitize_key( wp_unslash( $_POST['wpch_test'] ) ) : '';
		if ( '' !== $test ) {
			$this->send_test_event( $test );
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::SLUG . '&tab=' . $tab . '&updated=1' ) );
		exit;
	}
}
