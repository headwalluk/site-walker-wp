<?php
/**
 * Tabbed settings page for Site Walker.
 *
 * Tabs split into two families:
 *   - Settings-API-driven (Widget, Appearance) — wrapped in one options.php
 *     form. Submit button shown only when a Settings-API tab is active.
 *   - REST-driven (Connection, Chatbot, Geo, Usage) — each panel is its own
 *     mini-form, saved via the WP REST endpoints under
 *     /wp-json/site-walker/v1/admin/*.
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

$tabs = array(
	'connection' => __( 'Connection', 'site-walker' ),
	'widget'     => __( 'Widget', 'site-walker' ),
	'appearance' => __( 'Appearance', 'site-walker' ),
	'chatbot'    => __( 'Chatbot', 'site-walker' ),
	'geo'        => __( 'Geo', 'site-walker' ),
	'usage'      => __( 'Usage', 'site-walker' ),
	'sessions'   => __( 'Sessions', 'site-walker' ),
);

// Tabs whose body is driven by the Settings API.
$settings_api_tabs = array( 'widget', 'appearance' );

$nav_html = '';
foreach ( $tabs as $slug => $label ) {
	$nav_html .= sprintf(
		'<a href="#%1$s" class="nav-tab" data-tab="%1$s">%2$s</a>',
		esc_attr( $slug ),
		esc_html( $label )
	);
}

printf( '<div class="wrap site-walker-wp-settings">' );
printf( '<h1>%s</h1>', esc_html( get_admin_page_title() ) );
printf( '<nav class="nav-tab-wrapper wp-clearfix">%s</nav>', $nav_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $nav_html assembled with escaped parts.

// ---------------------------------------------------------------------
// Settings-API-driven panels — one shared options.php form.
// ---------------------------------------------------------------------
printf( '<form action="options.php" method="post" class="swwp-settings-form">' );
settings_fields( SETTINGS_GROUP );

foreach ( $settings_api_tabs as $slug ) {
	printf( '<div id="%1$s-panel" class="tab-panel" data-panel="%1$s">', esc_attr( $slug ) );
	do_settings_sections( ADMIN_PAGE_SLUG . '-' . $slug );
	printf( '</div>' );
}

submit_button( null, 'primary', 'submit', true, array( 'class' => 'button button-primary swwp-settings-submit' ) );

printf( '</form>' );

// ---------------------------------------------------------------------
// REST-driven panels — each loads from its own partial; saves via JS.
// ---------------------------------------------------------------------
$rest_tabs = array(
	'connection' => 'connection.php',
	'chatbot'    => 'chatbot.php',
	'geo'        => 'geo.php',
	'usage'      => 'usage.php',
	'sessions'   => 'sessions.php',
);

foreach ( $rest_tabs as $slug => $partial ) {
	$partial_path = \STWLK_PLUGIN_DIR . 'admin-templates/tabs/' . $partial;
	printf( '<div id="%1$s-panel" class="tab-panel tab-panel-rest" data-panel="%1$s">', esc_attr( $slug ) );
	if ( file_exists( $partial_path ) ) {
		require $partial_path;
	} else {
		printf( '<p class="notice notice-warning">%s</p>', esc_html__( 'Tab template not found. Re-install the plugin.', 'site-walker' ) );
	}
	printf( '</div>' );
}

printf( '</div>' );
