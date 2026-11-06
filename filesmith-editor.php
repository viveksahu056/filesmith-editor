<?php
/**
 * Plugin Name:       FileSmith Editor
 * Description:       A theme and plugin file editor with the native editor look, tabs, quick open, a PHP syntax check and automatic backups.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            WpWebGuru
 * Author URI:        https://wpwebguru.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       filesmith-editor
 * Domain Path:       /languages
 *
 * @package FileSmithEditor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FSED_VERSION', '1.0.0' );
define( 'FSED_FILE', __FILE__ );
define( 'FSED_PATH', plugin_dir_path( __FILE__ ) );
define( 'FSED_URL', plugin_dir_url( __FILE__ ) );
define( 'FSED_BASENAME', plugin_basename( __FILE__ ) );
define( 'FSED_MIN_PHP', '7.4' );
define( 'FSED_MIN_WP', '5.8' );

require_once FSED_PATH . 'includes/class-autoloader.php';
\FileSmithEditor\Autoloader::register();

register_activation_hook( FSED_FILE, array( '\FileSmithEditor\Activator', 'activate' ) );
register_deactivation_hook( FSED_FILE, array( '\FileSmithEditor\Deactivator', 'deactivate' ) );

/**
 * Boot the plugin.
 *
 * @return void
 */
function fsed_run() {
	\FileSmithEditor\Plugin::instance()->run();
}
add_action( 'plugins_loaded', 'fsed_run' );
