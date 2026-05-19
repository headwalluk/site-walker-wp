<?php
/**
 * Plugin constants.
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

// Removed : Text domain is a rare instance where an inline magic string is what we need
// const TEXT_DOMAIN = 'site-walker-wp';

const ADMIN_PAGE_SLUG  = 'site-walker-wp';
const ADMIN_CAPABILITY = 'manage_options';
const SETTINGS_GROUP   = 'site_walker_wp_options';

// wp_options keys (OPT_ prefix).
const OPT_API_URL          = 'site_walker_wp_api_url';
const OPT_POSITION         = 'site_walker_wp_position';
const OPT_OFFSET_X         = 'site_walker_wp_offset_x';
const OPT_OFFSET_Y         = 'site_walker_wp_offset_y';
const OPT_BUTTON_BG        = 'site_walker_wp_button_bg';
const OPT_BUTTON_FG        = 'site_walker_wp_button_fg';
const OPT_ACCENT_COLOR     = 'site_walker_wp_accent_color';
const OPT_ICON             = 'site_walker_wp_icon';
const OPT_HEADER_TEXT      = 'site_walker_wp_header_text';
const OPT_PLACEHOLDER_TEXT = 'site_walker_wp_placeholder_text';
const OPT_ENABLED          = 'site_walker_wp_enabled';

// Default values (DEF_ prefix).
const DEF_API_URL          = 'https://api.site-walker.net';
const DEF_POSITION         = 'bottom-right';
const DEF_OFFSET_X         = 24;
const DEF_OFFSET_Y         = 24;
const DEF_BUTTON_BG        = '#2563eb';
const DEF_BUTTON_FG        = '#ffffff';
const DEF_ACCENT_COLOR     = '#2563eb';
const DEF_ICON             = 'chat';
const DEF_HEADER_TEXT      = 'Chat';
const DEF_PLACEHOLDER_TEXT = 'Type a message…';

// Position choices.
const POSITION_CHOICES = array(
	'bottom-right' => 'Bottom right',
	'bottom-left'  => 'Bottom left',
	'top-right'    => 'Top right',
	'top-left'     => 'Top left',
);

// Built-in icon choices.
const ICON_CHOICES = array(
	'chat'     => 'Chat bubble',
	'question' => 'Question mark',
	'sparkle'  => 'Sparkle',
);

// localStorage / API tuning - exposed to JS via the data layer.
const PROBE_COOLDOWN_SECONDS = 3600; // How long to wait before re-probing an unreachable API.
const PROBE_AVAILABLE_TTL    = 86400; // How long to trust a successful probe.
const MAX_MESSAGE_LENGTH     = 8000;
