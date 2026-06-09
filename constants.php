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
const OPT_TRUSTED_HOSTS    = 'site_walker_wp_trusted_hosts'; // array<string>; hosts whose URLs in assistant replies become clickable

// Admin-area connection state — set via REST, never via the Settings API.
// Both are autoload=no (set at update_option time) since they only matter on
// admin requests and admin REST handler invocations.
const OPT_ADMIN_KEY        = 'site_walker_wp_admin_key';
const OPT_CHATBOT_SLUG     = 'site_walker_wp_chatbot_slug';

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
const DEF_TRUSTED_HOSTS    = array();

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

// Admin REST namespace + route prefix. All M7 admin proxy routes mount under
// /wp-json/site-walker/v1/admin/*.
const ADMIN_REST_NAMESPACE   = 'site-walker/v1';
const ADMIN_REST_ROOT        = 'admin';

// Account admin key shape — matches the upstream sw_ + 43 base64url chars.
const ADMIN_KEY_REGEX        = '/^sw_[A-Za-z0-9_-]{43}$/';

// Context (system) blocks. Name pattern + reserved names mirror the upstream
// `/admin/chatbots/{slug}/blocks` surface so the operator gets a friendly
// error before the round-trip rather than a raw upstream 400.
const BLOCK_NAME_REGEX       = '/^[A-Za-z0-9_-]+$/';
const BLOCK_MAX_BYTES        = 65536; // 64 KB — upstream rejects larger with 413.
// Names the upstream PUT refuses: PERSONA lives in the chatbots.persona DB
// column (set on the Chatbot tab); HANDOFF_FINAL is a hardcoded built-in.
// HANDOFF_SOFT / HANDOFF_HARD are deliberately writable and NOT reserved.
const RESERVED_BLOCK_NAMES   = array( 'PERSONA', 'HANDOFF_FINAL' );

// GitHub-Releases auto-updater. Used by Github_Updater to poll for new
// versions; release artifacts come from the `release.yml` workflow that
// builds a `site-walker-wp.zip` (stable name) + a `site-walker-wp-<ver>.zip`
// (versioned) on every `v*.*.*` tag push.
const UPDATER_GITHUB_REPO    = 'headwalluk/site-walker-wp';
const UPDATER_CACHE_KEY      = 'site_walker_wp_latest_release';
const UPDATER_CACHE_TTL      = HOUR_IN_SECONDS; // 1h — at most one GitHub round-trip per WP update cycle.
