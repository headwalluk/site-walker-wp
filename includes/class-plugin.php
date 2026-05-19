<?php
/**
 * Main plugin orchestrator.
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

class Plugin {

	private ?Settings $settings = null;

	private ?Admin_Hooks $admin_hooks = null;

	private ?Public_Hooks $public_hooks = null;

	// Moved into run()
	// public function __construct() {
	// $this->settings = new Settings();
	// }

	/**
	 * Register all hooks.
	 */
	public function run(): void {
		$this->settings = new Settings();

		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Settings must register early so admin_init picks them up.
		add_action( 'admin_init', array( $this->settings, 'register_settings' ) );

		$public_hooks = $this->get_public_hooks();

		if ( is_admin() ) {
			$admin_hooks = $this->get_admin_hooks();

			add_action( 'admin_menu', array( $admin_hooks, 'register_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $admin_hooks, 'enqueue_assets' ) );
		} else {
			add_action( 'wp_enqueue_scripts', array( $public_hooks, 'enqueue_assets' ) );
			add_action( 'wp_footer', array( $public_hooks, 'render_widget_container' ) );
		}
	}

	public function load_textdomain(): void {
		// load_plugin_textdomain( 'site-walker', false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );
		load_plugin_textdomain( 'site-walker', false, STWLK_PLUGIN_DIR . '/languages' );
	}

	public function get_settings(): Settings {
		return $this->settings;
	}

	public function get_admin_hooks(): Admin_Hooks {
		if ( is_null( $this->admin_hooks ) ) {
			$this->admin_hooks = new Admin_Hooks();
		}

		return $this->admin_hooks;
	}

	public function get_public_hooks(): Public_Hooks {
		if ( is_null( $this->public_hooks ) ) {
			$this->public_hooks = new Public_Hooks();
		}

		return $this->public_hooks;
	}
}
