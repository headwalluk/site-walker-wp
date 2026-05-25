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

		// Shared assistant-message formatter. Same handle the front-end widget
		// uses (see class-public-hooks.php); WP de-dupes by handle so loading
		// it from both sides is safe. Used by the Sessions tab to render
		// reviewed conversations identically to the live widget.
		wp_enqueue_script( 'site-walker-wp-formatter', \STWLK_PLUGIN_URL . 'assets/shared/formatter.js', array(), \STWLK_PLUGIN_VERSION, true );

		wp_enqueue_script( 'site-walker-wp-admin', \STWLK_PLUGIN_URL . 'assets/admin/admin.js', array( 'wp-color-picker', 'site-walker-wp-formatter' ), \STWLK_PLUGIN_VERSION, true );

		// REST config the admin JS needs to talk to /wp-json/site-walker/v1/admin/*.
		// Nonce is the standard 'wp_rest' nonce — WP REST will accept it on X-WP-Nonce.
		$trusted_hosts = get_option( OPT_TRUSTED_HOSTS, DEF_TRUSTED_HOSTS );
		if ( ! is_array( $trusted_hosts ) ) {
			$trusted_hosts = array();
		}

		// WP site timezone, surfaced to the Chatbot tab for the "use this
		// site's timezone" button. Only useful if it's an IANA identifier;
		// `wp_timezone_string()` can also return a UTC offset like "+00:00"
		// when the WP site is configured by offset rather than by zone, and
		// the upstream API only accepts IANA names. Skip the offset case.
		$wp_tz      = function_exists( 'wp_timezone_string' ) ? (string) wp_timezone_string() : '';
		$wp_tz_iana = ( '' !== $wp_tz && '+' !== $wp_tz[0] && '-' !== $wp_tz[0] ) ? $wp_tz : '';

		$config = array(
			'restRoot'       => esc_url_raw( rest_url( ADMIN_REST_NAMESPACE . '/' . ADMIN_REST_ROOT ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			// Same list the widget gets; lets the Sessions-tab message
			// formatter render the same link auto-linking the visitor saw.
			'trustedHosts'   => array_values( $trusted_hosts ),
			'wpTimezone'     => $wp_tz_iana, // empty string if not IANA-shaped
			// This site's canonical origin, used by the Connection tab to
			// surface the expected origin in informational + error UI.
			'expectedOrigin' => get_site_origin(),
			'strings'        => array(
				'unexpectedError'   => __( 'Something went wrong. Please try again.', 'site-walker' ),
				'bearerInvalid'     => __( 'Admin key not recognised. Check that it\'s the right key and hasn\'t been revoked.', 'site-walker' ),
				'wrongScope'        => __( 'This is a provisioning key, not an account admin key. Mint one with `./bin/sw account add-admin-key`.', 'site-walker' ),
				'notFound'          => __( 'No chatbot found with this slug.', 'site-walker' ),
				'transportError'    => __( 'Couldn\'t reach the API server. Check the URL is correct and the server is up.', 'site-walker' ),
				'noChatbots'        => __( 'Admin key works, but no chatbots were found for the account. Create one with `./bin/sw chatbot create`.', 'site-walker' ),
				/* translators: %s: site origin like https://example.com */
				'noOriginMatch'     => __( 'No chatbot in this account has %1$s on its origin allowlist. Add it upstream with: sw chatbot origins add <slug> %2$s', 'site-walker' ),
				'originMismatch'    => __( 'The saved chatbot no longer lists this site as an allowed origin. Re-save your admin key to find the right chatbot, or add this origin upstream.', 'site-walker' ),
				'connectionOk'      => __( 'Connection OK.', 'site-walker' ),
				'savedAndConnected' => __( 'Saved. Connected to:', 'site-walker' ),
				'saved'             => __( 'Saved.', 'site-walker' ),
				'clearConfirm'      => __( 'Clear the saved admin key? You\'ll need to paste it again to manage the chatbot from here.', 'site-walker' ),
				'notConnected'      => __( 'Not connected.', 'site-walker' ),
			),
		);

		wp_add_inline_script( 'site-walker-wp-admin', 'window.siteWalkerWPAdmin = ' . wp_json_encode( $config ) . ';', 'before' );
	}
}
