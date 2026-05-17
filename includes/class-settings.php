<?php
/**
 * Settings registration and sanitisation.
 *
 * @package Site_Walker_WP
 */

namespace Site_Walker_WP;

defined( 'ABSPATH' ) || die();

class Settings {

	/**
	 * Register all options + Settings API sections / fields.
	 */
	public function register_settings(): void {
		$this->register_option( OPT_ENABLED, 'boolean', false, array( $this, 'sanitize_bool' ) );
		$this->register_option( OPT_API_URL, 'string', DEF_API_URL, array( $this, 'sanitize_url' ) );
		$this->register_option( OPT_POSITION, 'string', DEF_POSITION, array( $this, 'sanitize_position' ) );
		$this->register_option( OPT_OFFSET_X, 'integer', DEF_OFFSET_X, array( $this, 'sanitize_offset' ) );
		$this->register_option( OPT_OFFSET_Y, 'integer', DEF_OFFSET_Y, array( $this, 'sanitize_offset' ) );
		$this->register_option( OPT_BUTTON_BG, 'string', DEF_BUTTON_BG, array( $this, 'sanitize_color' ) );
		$this->register_option( OPT_BUTTON_FG, 'string', DEF_BUTTON_FG, array( $this, 'sanitize_color' ) );
		$this->register_option( OPT_ACCENT_COLOR, 'string', DEF_ACCENT_COLOR, array( $this, 'sanitize_color' ) );
		$this->register_option( OPT_ICON, 'string', DEF_ICON, array( $this, 'sanitize_icon' ) );
		$this->register_option( OPT_HEADER_TEXT, 'string', DEF_HEADER_TEXT, 'sanitize_text_field' );
		$this->register_option( OPT_PLACEHOLDER_TEXT, 'string', DEF_PLACEHOLDER_TEXT, 'sanitize_text_field' );

		$this->register_sections_and_fields();
	}

	/**
	 * Sanitise a value coerced to a bool.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize_bool( $value ): bool {
		return (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitise an API base URL. Empty values fall back to the default so the
	 * front-end never inherits a broken value.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize_url( $value ): string {
		$raw   = is_string( $value ) ? trim( $value ) : '';
		$clean = esc_url_raw( $raw );
		$final = '' === $clean ? DEF_API_URL : untrailingslashit( $clean );

		return $final;
	}

	/**
	 * Sanitise the position dropdown - only allow known keys.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize_position( $value ): string {
		$key   = is_string( $value ) ? $value : '';
		$valid = array_key_exists( $key, POSITION_CHOICES );
		$final = $valid ? $key : DEF_POSITION;

		return $final;
	}

	/**
	 * Sanitise an offset in pixels - clamped to a sensible range.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize_offset( $value ): int {
		$int   = absint( $value );
		$final = min( 200, max( 0, $int ) );

		return $final;
	}

	/**
	 * Sanitise a hex colour. Falls back to a sensible default if invalid.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize_color( $value ): string {
		$raw   = is_string( $value ) ? trim( $value ) : '';
		$clean = sanitize_hex_color( $raw );
		$final = is_string( $clean ) ? $clean : '#000000';

		return $final;
	}

	/**
	 * Sanitise the icon key - only allow known values.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize_icon( $value ): string {
		$key   = is_string( $value ) ? $value : '';
		$valid = array_key_exists( $key, ICON_CHOICES );
		$final = $valid ? $key : DEF_ICON;

		return $final;
	}

	/**
	 * Helper - thin wrapper around register_setting().
	 *
	 * @param string   $option_name Option key.
	 * @param string   $type        WP setting type.
	 * @param mixed    $default     Default value.
	 * @param callable $sanitiser   Sanitisation callback.
	 */
	private function register_option( string $option_name, string $type, $default, $sanitiser ): void {
		register_setting(
			SETTINGS_GROUP,
			$option_name,
			array(
				'type'              => $type,
				'sanitize_callback' => $sanitiser,
				'default'           => $default,
			)
		);
	}

	/**
	 * Register Settings API sections and fields for the admin page.
	 */
	private function register_sections_and_fields(): void {
		// General section.
		add_settings_section(
			'site_walker_wp_general',
			__( 'General', 'site-walker-wp' ),
			'__return_false',
			ADMIN_PAGE_SLUG . '-general'
		);

		add_settings_field(
			OPT_ENABLED,
			__( 'Enable chat widget', 'site-walker-wp' ),
			array( $this, 'render_field_enabled' ),
			ADMIN_PAGE_SLUG . '-general',
			'site_walker_wp_general'
		);

		add_settings_field(
			OPT_API_URL,
			__( 'API server URL', 'site-walker-wp' ),
			array( $this, 'render_field_api_url' ),
			ADMIN_PAGE_SLUG . '-general',
			'site_walker_wp_general'
		);

		// Appearance section.
		add_settings_section(
			'site_walker_wp_appearance',
			__( 'Appearance', 'site-walker-wp' ),
			'__return_false',
			ADMIN_PAGE_SLUG . '-appearance'
		);

		add_settings_field(
			OPT_ICON,
			__( 'Button icon', 'site-walker-wp' ),
			array( $this, 'render_field_icon' ),
			ADMIN_PAGE_SLUG . '-appearance',
			'site_walker_wp_appearance'
		);

		add_settings_field(
			OPT_POSITION,
			__( 'Position', 'site-walker-wp' ),
			array( $this, 'render_field_position' ),
			ADMIN_PAGE_SLUG . '-appearance',
			'site_walker_wp_appearance'
		);

		add_settings_field(
			OPT_OFFSET_X,
			__( 'Horizontal offset (px)', 'site-walker-wp' ),
			array( $this, 'render_field_offset_x' ),
			ADMIN_PAGE_SLUG . '-appearance',
			'site_walker_wp_appearance'
		);

		add_settings_field(
			OPT_OFFSET_Y,
			__( 'Vertical offset (px)', 'site-walker-wp' ),
			array( $this, 'render_field_offset_y' ),
			ADMIN_PAGE_SLUG . '-appearance',
			'site_walker_wp_appearance'
		);

		add_settings_field(
			OPT_BUTTON_BG,
			__( 'Button background', 'site-walker-wp' ),
			array( $this, 'render_field_button_bg' ),
			ADMIN_PAGE_SLUG . '-appearance',
			'site_walker_wp_appearance'
		);

		add_settings_field(
			OPT_BUTTON_FG,
			__( 'Button icon colour', 'site-walker-wp' ),
			array( $this, 'render_field_button_fg' ),
			ADMIN_PAGE_SLUG . '-appearance',
			'site_walker_wp_appearance'
		);

		add_settings_field(
			OPT_ACCENT_COLOR,
			__( 'Chat accent colour', 'site-walker-wp' ),
			array( $this, 'render_field_accent' ),
			ADMIN_PAGE_SLUG . '-appearance',
			'site_walker_wp_appearance'
		);

		add_settings_field(
			OPT_HEADER_TEXT,
			__( 'Header text', 'site-walker-wp' ),
			array( $this, 'render_field_header_text' ),
			ADMIN_PAGE_SLUG . '-appearance',
			'site_walker_wp_appearance'
		);

		add_settings_field(
			OPT_PLACEHOLDER_TEXT,
			__( 'Input placeholder', 'site-walker-wp' ),
			array( $this, 'render_field_placeholder' ),
			ADMIN_PAGE_SLUG . '-appearance',
			'site_walker_wp_appearance'
		);
	}

	// ---------------------------------------------------------------------
	// Field renderers - small wrappers around shared helpers.
	// ---------------------------------------------------------------------

	public function render_field_enabled(): void {
		$value = (bool) filter_var( get_option( OPT_ENABLED, false ), FILTER_VALIDATE_BOOLEAN );
		printf(
			'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( OPT_ENABLED ),
			checked( $value, true, false ),
			esc_html__( 'Inject the chat widget on the front-end.', 'site-walker-wp' )
		);
	}

	public function render_field_api_url(): void {
		$value = (string) get_option( OPT_API_URL, DEF_API_URL );
		printf(
			'<input type="url" class="regular-text code" name="%1$s" value="%2$s" placeholder="%3$s" /><p class="description">%4$s</p>',
			esc_attr( OPT_API_URL ),
			esc_attr( $value ),
			esc_attr( DEF_API_URL ),
			esc_html__( 'Base URL of the Site Walker API (no trailing slash).', 'site-walker-wp' )
		);
	}

	public function render_field_icon(): void {
		$value = (string) get_option( OPT_ICON, DEF_ICON );
		$opts  = '';
		foreach ( ICON_CHOICES as $key => $label ) {
			$opts .= sprintf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $key ),
				selected( $value, $key, false ),
				esc_html( $label )
			);
		}
		printf( '<select name="%1$s">%2$s</select>', esc_attr( OPT_ICON ), $opts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $opts already escaped.
	}

	public function render_field_position(): void {
		$value = (string) get_option( OPT_POSITION, DEF_POSITION );
		$opts  = '';
		foreach ( POSITION_CHOICES as $key => $label ) {
			$opts .= sprintf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $key ),
				selected( $value, $key, false ),
				esc_html( $label )
			);
		}
		printf( '<select name="%1$s">%2$s</select>', esc_attr( OPT_POSITION ), $opts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $opts already escaped.
	}

	public function render_field_offset_x(): void {
		$value = (int) get_option( OPT_OFFSET_X, DEF_OFFSET_X );
		$this->render_number_field( OPT_OFFSET_X, $value );
	}

	public function render_field_offset_y(): void {
		$value = (int) get_option( OPT_OFFSET_Y, DEF_OFFSET_Y );
		$this->render_number_field( OPT_OFFSET_Y, $value );
	}

	public function render_field_button_bg(): void {
		$value = (string) get_option( OPT_BUTTON_BG, DEF_BUTTON_BG );
		$this->render_color_field( OPT_BUTTON_BG, $value );
	}

	public function render_field_button_fg(): void {
		$value = (string) get_option( OPT_BUTTON_FG, DEF_BUTTON_FG );
		$this->render_color_field( OPT_BUTTON_FG, $value );
	}

	public function render_field_accent(): void {
		$value = (string) get_option( OPT_ACCENT_COLOR, DEF_ACCENT_COLOR );
		$this->render_color_field( OPT_ACCENT_COLOR, $value );
	}

	public function render_field_header_text(): void {
		$value = (string) get_option( OPT_HEADER_TEXT, DEF_HEADER_TEXT );
		printf(
			'<input type="text" class="regular-text" name="%1$s" value="%2$s" />',
			esc_attr( OPT_HEADER_TEXT ),
			esc_attr( $value )
		);
	}

	public function render_field_placeholder(): void {
		$value = (string) get_option( OPT_PLACEHOLDER_TEXT, DEF_PLACEHOLDER_TEXT );
		printf(
			'<input type="text" class="regular-text" name="%1$s" value="%2$s" />',
			esc_attr( OPT_PLACEHOLDER_TEXT ),
			esc_attr( $value )
		);
	}

	private function render_number_field( string $name, int $value ): void {
		printf(
			'<input type="number" min="0" max="200" step="1" name="%1$s" value="%2$d" /> <span class="description">%3$s</span>',
			esc_attr( $name ),
			(int) $value,
			esc_html__( 'pixels', 'site-walker-wp' )
		);
	}

	private function render_color_field( string $name, string $value ): void {
		printf(
			'<input type="text" class="site-walker-wp-color-field" name="%1$s" value="%2$s" data-default-color="%2$s" />',
			esc_attr( $name ),
			esc_attr( $value )
		);
	}
}
