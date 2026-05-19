<?php
/**
 * Admin menu registration and asset loading.
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

class Admin_Hooks {

	/**
	 * Hook suffix returned by add_menu_page() - used to gate asset loading.
	 */
	private string $hook_suffix = '';

	/**
	 * Register the top-level admin menu.
	 */
	public function register_menu(): void {
		$this->hook_suffix = (string) add_menu_page(
			__( 'Site Walker', 'site-walker' ),
			__( 'Site Walker', 'site-walker' ),
			ADMIN_CAPABILITY,
			ADMIN_PAGE_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-format-chat',
			80
		);
	}

	/**
	 * Render the tabbed settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( ADMIN_CAPABILITY ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'site-walker' ) );
		}

		require \STWLK_PLUGIN_DIR . 'admin-templates/settings-page.php';
	}

	/**
	 * Enqueue admin-only assets on our settings page.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_style( 'site-walker-wp-admin', \STWLK_PLUGIN_URL . 'assets/admin/admin.css', array(), \STWLK_PLUGIN_VERSION );

		wp_enqueue_script( 'site-walker-wp-admin', \STWLK_PLUGIN_URL . 'assets/admin/admin.js', array( 'wp-color-picker' ), \STWLK_PLUGIN_VERSION, true );
	}
}
