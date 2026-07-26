<?php
/**
 * Plugin Name:       Conversion Relay
 * Plugin URI:        https://github.com/jgalea/conversion-relay
 * Author:            Jean Galea
 * Author URI:        https://github.com/jgalea
 * Description:       No-code bridge from WordPress plugin events to analytics and ad platforms. Open alternative to tag-manager conversion tracking.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       conversion-relay
 */

declare( strict_types=1 );

namespace WPConversionHub;

defined( 'ABSPATH' ) || exit;

define( 'WPCH_VERSION', '0.1.0' );
define( 'WPCH_FILE', __FILE__ );
define( 'WPCH_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCH_URL', plugin_dir_url( __FILE__ ) );

$wpch_autoload = WPCH_DIR . 'vendor/autoload.php';
if ( is_readable( $wpch_autoload ) ) {
	require_once $wpch_autoload;
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			if ( strpos( $class, 'WPConversionHub\\' ) !== 0 ) {
				return;
			}
			$relative = substr( $class, strlen( 'WPConversionHub\\' ) );
			$path     = WPCH_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	);
}

$wpch_action_scheduler = WPCH_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
if ( is_readable( $wpch_action_scheduler ) ) {
	require_once $wpch_action_scheduler;
}

register_activation_hook( __FILE__, array( Storage\Schema::class, 'install' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->boot();
	}
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	add_action(
		'cli_init',
		static function (): void {
			\WP_CLI::add_command( 'conversion-hub', Cli\Commands::class );
		}
	);
}
