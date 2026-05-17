<?php
/**
 * Front-end asset loading and widget injection.
 *
 * @package Site_Walker_WP
 */

namespace Site_Walker_WP;

defined( 'ABSPATH' ) || die();

class Public_Hooks {

	/**
	 * Enqueue widget CSS / JS on the front-end.
	 */
	public function enqueue_assets(): void {
		if ( ! is_widget_renderable() ) {
			return;
		}

		wp_enqueue_style(
			'site-walker-wp-widget',
			PLUGIN_URL . 'assets/public/widget.css',
			array(),
			PLUGIN_VERSION
		);

		wp_enqueue_script(
			'site-walker-wp-widget',
			PLUGIN_URL . 'assets/public/widget.js',
			array(),
			PLUGIN_VERSION,
			true
		);

		wp_add_inline_script(
			'site-walker-wp-widget',
			'window.siteWalkerWP = ' . wp_json_encode( get_widget_config() ) . ';',
			'before'
		);
	}

	/**
	 * Inject the widget mount point into wp_footer.
	 */
	public function render_widget_container(): void {
		if ( ! is_widget_renderable() ) {
			return;
		}

		$config = get_widget_config();

		$style = sprintf(
			'--swwp-button-bg:%1$s;--swwp-button-fg:%2$s;--swwp-accent:%3$s;--swwp-offset-x:%4$dpx;--swwp-offset-y:%5$dpx;',
			esc_attr( $config['buttonBg'] ),
			esc_attr( $config['buttonFg'] ),
			esc_attr( $config['accentColor'] ),
			(int) $config['offsetX'],
			(int) $config['offsetY']
		);

		printf(
			'<div class="site-walker-wp swwp-pos-%1$s" data-position="%1$s" style="%2$s" hidden>',
			esc_attr( $config['position'] ),
			esc_attr( $style )
		);

		// Floating launcher.
		printf(
			'<button type="button" class="swwp-launcher button" aria-label="%1$s" aria-expanded="false">%2$s</button>',
			esc_attr__( 'Open chat', 'site-walker-wp' ),
			wp_kses(
				get_icon_svg( $config['icon'] ),
				array(
					'svg'    => array(
						'xmlns'         => true,
						'viewbox'       => true,
						'fill'          => true,
						'stroke'        => true,
						'stroke-width'  => true,
						'stroke-linecap' => true,
						'stroke-linejoin' => true,
					),
					'path'   => array( 'd' => true ),
					'circle' => array( 'cx' => true, 'cy' => true, 'r' => true ),
					'line'   => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ),
				)
			)
		);

		// Expanded panel - hidden until launcher click.
		$panel  = sprintf( '<div class="swwp-panel" role="dialog" aria-label="%s" hidden>', esc_attr__( 'Chat', 'site-walker-wp' ) );
		$panel .= sprintf(
			'<div class="swwp-panel-header"><span class="swwp-panel-title">%1$s</span><button type="button" class="swwp-close button" aria-label="%2$s">&times;</button></div>',
			esc_html( $config['headerText'] ),
			esc_attr__( 'Close chat', 'site-walker-wp' )
		);
		$panel .= '<div class="swwp-messages" aria-live="polite"></div>';
		$panel .= sprintf(
			'<form class="swwp-input-row" autocomplete="off"><textarea class="swwp-input" rows="1" placeholder="%1$s" maxlength="%2$d"></textarea><button type="submit" class="swwp-send button" aria-label="%3$s">&rarr;</button></form>',
			esc_attr( $config['placeholderText'] ),
			(int) $config['maxMessageLen'],
			esc_attr__( 'Send message', 'site-walker-wp' )
		);
		$panel .= '</div>';

		echo $panel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All interpolated values escaped above.

		printf( '</div>' );
	}
}
