<?php
/**
 * Internal helpers shared across the plugin.
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

function get_plugin(): Plugin {
	global $stwlk_plugin;
	return $stwlk_plugin;
}

/**
 * Read the current widget configuration as a single associative array.
 *
 * @return array<string, mixed>
 */
function get_widget_config(): array {
	$config = array(
		'enabled'         => (bool) filter_var( get_option( OPT_ENABLED, true ), FILTER_VALIDATE_BOOLEAN ),
		'apiUrl'          => untrailingslashit( (string) get_option( OPT_API_URL, DEF_API_URL ) ),
		'position'        => (string) get_option( OPT_POSITION, DEF_POSITION ),
		'offsetX'         => (int) get_option( OPT_OFFSET_X, DEF_OFFSET_X ),
		'offsetY'         => (int) get_option( OPT_OFFSET_Y, DEF_OFFSET_Y ),
		'buttonBg'        => (string) get_option( OPT_BUTTON_BG, DEF_BUTTON_BG ),
		'buttonFg'        => (string) get_option( OPT_BUTTON_FG, DEF_BUTTON_FG ),
		'accentColor'     => (string) get_option( OPT_ACCENT_COLOR, DEF_ACCENT_COLOR ),
		'icon'            => (string) get_option( OPT_ICON, DEF_ICON ),
		'headerText'      => (string) get_option( OPT_HEADER_TEXT, DEF_HEADER_TEXT ),
		'placeholderText' => (string) get_option( OPT_PLACEHOLDER_TEXT, DEF_PLACEHOLDER_TEXT ),
		'probeCooldownMs' => PROBE_COOLDOWN_SECONDS * 1000,
		'probeTtlMs'      => PROBE_AVAILABLE_TTL * 1000,
		'maxMessageLen'   => MAX_MESSAGE_LENGTH,
		'storagePrefix'   => 'site-walker-wp',
	);

	return $config;
}

/**
 * Whether the widget is configured well enough to render on the front-end.
 */
function is_widget_renderable(): bool {
	$config     = get_widget_config();
	$is_enabled = (bool) $config['enabled'];
	$has_url    = ! empty( $config['apiUrl'] ) && filter_var( $config['apiUrl'], FILTER_VALIDATE_URL );

	return $is_enabled && $has_url;
}

/**
 * Return the SVG markup for a built-in icon key.
 */
function get_icon_svg( string $icon_key ): string {
	$icons = array(
		'chat'     =>
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>',
		'question' =>
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
		'sparkle'  =>
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.6L19.5 10l-5.6 1.4L12 17l-1.9-5.6L4.5 10l5.6-1.4L12 3z"></path></svg>',
	);

	$svg = $icons[ $icon_key ] ?? $icons['chat'];

	return $svg;
}
