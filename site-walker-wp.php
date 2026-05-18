<?php
/**
 * Plugin Name:       Site Walker
 * Plugin URI:        https://site-walker.net/
 * Description:       Front-end chat widget powered by a Site Walker API instance.
 * Version:           0.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Headwall Hosting
 * Author URI:        https://headwall-hosting.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       site-walker-wp
 * Domain Path:       /languages
 *
 * @package Site_Walker_WP
 */

namespace Site_Walker_WP;

defined( 'ABSPATH' ) || die();

define( __NAMESPACE__ . '\\PLUGIN_FILE', __FILE__ );
define( __NAMESPACE__ . '\\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( __NAMESPACE__ . '\\PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( __NAMESPACE__ . '\\PLUGIN_VERSION', '0.2.0' );

require_once PLUGIN_DIR . 'constants.php';
require_once PLUGIN_DIR . 'functions-private.php';
require_once PLUGIN_DIR . 'includes/class-settings.php';
require_once PLUGIN_DIR . 'includes/class-admin-hooks.php';
require_once PLUGIN_DIR . 'includes/class-public-hooks.php';
require_once PLUGIN_DIR . 'includes/class-plugin.php';

global $site_walker_wp_instance;
$site_walker_wp_instance = new Plugin();
$site_walker_wp_instance->run();

/**
 * Accessor for the global plugin instance.
 */
function plugin(): Plugin {
	global $site_walker_wp_instance;
	return $site_walker_wp_instance;
}
