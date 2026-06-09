<?php
/**
 * Plugin Name:       Site Walker
 * Plugin URI:        https://site-walker.net/
 * Description:       Front-end chat widget powered by a Site Walker API instance.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Headwall Hosting
 * Author URI:        https://headwall-hosting.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       site-walker
 * Domain Path:       /languages
 *
 * @package Site_Walker
 */

// The main entry point file should not have a namespace scope.
// namespace Site_Walker_WP;

defined( 'ABSPATH' ) || die();

define( 'STWLK_PLUGIN_VERSION', '1.2.0' );
define( 'STWLK_PLUGIN_FILE', __FILE__ );
define( 'STWLK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STWLK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// define( __NAMESPACE__ . '\\PLUGIN_FILE', __FILE__ );
// define( __NAMESPACE__ . '\\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
// define( __NAMESPACE__ . '\\PLUGIN_URL', plugin_dir_url( __FILE__ ) );
// define( __NAMESPACE__ . '\\PLUGIN_VERSION', '0.3.0' );

require_once STWLK_PLUGIN_DIR . 'constants.php';
require_once STWLK_PLUGIN_DIR . 'includes/class-admin-api-client.php';
require_once STWLK_PLUGIN_DIR . 'functions-private.php';

// Uncomment this if we need to add some public functions.
// require_once STWLK_PLUGIN_DIR . 'functions.php';

require_once STWLK_PLUGIN_DIR . 'includes/class-settings.php';
require_once STWLK_PLUGIN_DIR . 'includes/class-admin-hooks.php';
require_once STWLK_PLUGIN_DIR . 'includes/class-admin-rest.php';
require_once STWLK_PLUGIN_DIR . 'includes/class-public-hooks.php';
require_once STWLK_PLUGIN_DIR . 'includes/class-github-updater.php';
require_once STWLK_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Bootstrap the plugin.
 *
 * Instantiates the main Plugin object, stores it on the `$stwlk_plugin` global
 * so other code can reach it via {@see stwlk_get_site_walker()}, and registers
 * all WordPress hooks.
 */
function stwlk_plugin_run(): void {
	global $stwlk_plugin;
	$stwlk_plugin = new Site_Walker\Plugin();
	$stwlk_plugin->run();
}
stwlk_plugin_run();
