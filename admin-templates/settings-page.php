<?php
/**
 * Tabbed settings page for Site Walker.
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

$tabs = array(
	'general'    => __( 'General', 'site-walker' ),
	'appearance' => __( 'Appearance', 'site-walker' ),
);

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

printf( '<form action="options.php" method="post">' );

settings_fields( SETTINGS_GROUP );

foreach ( $tabs as $slug => $label ) {
	printf(
		'<div id="%1$s-panel" class="tab-panel" data-panel="%1$s">',
		esc_attr( $slug )
	);
	do_settings_sections( ADMIN_PAGE_SLUG . '-' . $slug );
	printf( '</div>' );
}

submit_button();

printf( '</form>' );
printf( '</div>' );
