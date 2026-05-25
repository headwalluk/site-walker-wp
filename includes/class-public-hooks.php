<?php
/**
 * Front-end asset loading and widget injection.
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

class Public_Hooks {

	/**
	 * Enqueue widget CSS / JS on the front-end.
	 */
	public function enqueue_assets(): void {
		if ( ! is_widget_renderable() ) {
			return;
		}

		wp_enqueue_style( 'site-walker-wp-widget', \STWLK_PLUGIN_URL . 'assets/public/widget.css', array(), \STWLK_PLUGIN_VERSION );

		wp_enqueue_script( 'site-walker-wp-widget', \STWLK_PLUGIN_URL . 'assets/public/widget.js', array(), \STWLK_PLUGIN_VERSION, true );

		$config = get_widget_config();

		// Admin-mode session minting (M8). When the current page is being
		// rendered to a logged-in user who can manage_options, expose the
		// admin-session REST URL + nonce so the widget can mint a server-
		// proxied admin-mode session instead of the standard /sessions one.
		// The nonce is the same 'wp_rest' nonce wp-admin uses, and is
		// short-lived + tied to the logged-in user — a non-admin viewer can't
		// just grab one off the page.
		if ( is_user_logged_in() && current_user_can( ADMIN_CAPABILITY ) ) {
			$config['adminSession'] = array(
				'url'   => esc_url_raw( rest_url( ADMIN_REST_NAMESPACE . '/admin-session' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			);
		}

		wp_add_inline_script( 'site-walker-wp-widget', 'window.siteWalkerWP = ' . wp_json_encode( $config ) . ';', 'before' );
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

		// data-is-logged-in is the signal the widget JS reads to know it
		// should mint an admin-mode session instead of the standard one
		// (M8). It's not a credential — a non-admin who sets it manually
		// would still fail the manage_options check on the WP REST route.
		$is_admin_user = is_user_logged_in() && current_user_can( ADMIN_CAPABILITY );
		$admin_attr    = $is_admin_user ? ' data-is-logged-in="1"' : '';

		printf( '<div class="site-walker-wp swwp-pos-%1$s" data-position="%1$s" style="%2$s"%3$s hidden>', esc_attr( $config['position'] ), esc_attr( $style ), $admin_attr ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $admin_attr is a constant literal.

		// Floating launcher.
		printf(
			'<button type="button" class="swwp-launcher button" aria-label="%1$s" aria-expanded="false">%2$s</button>',
			esc_attr__( 'Open chat', 'site-walker' ),
			wp_kses(
				get_icon_svg( $config['icon'] ),
				array(
					'svg'    => array(
						'xmlns'           => true,
						'viewbox'         => true,
						'fill'            => true,
						'stroke'          => true,
						'stroke-width'    => true,
						'stroke-linecap'  => true,
						'stroke-linejoin' => true,
					),
					'path'   => array( 'd' => true ),
					'circle' => array(
						'cx' => true,
						'cy' => true,
						'r'  => true,
					),
					'line'   => array(
						'x1' => true,
						'y1' => true,
						'x2' => true,
						'y2' => true,
					),
				)
			)
		);

		// Expanded panel - hidden until launcher click.
		$panel  = sprintf( '<div class="swwp-panel" role="dialog" aria-label="%s" hidden>', esc_attr__( 'Chat', 'site-walker' ) );
		$panel .= sprintf(
			'<div class="swwp-panel-header"><span class="swwp-panel-title">%1$s</span><button type="button" class="swwp-close button" aria-label="%2$s">&times;</button></div>',
			esc_html( $config['headerText'] ),
			esc_attr__( 'Close chat', 'site-walker' )
		);
		$panel .= '<div class="swwp-messages" aria-live="polite"></div>';
		$panel .= sprintf(
			'<form class="swwp-input-row" autocomplete="off"><textarea class="swwp-input" rows="2" placeholder="%1$s" maxlength="%2$d"></textarea><button type="submit" class="swwp-send button" aria-label="%3$s">&rarr;</button></form>',
			esc_attr( $config['placeholderText'] ),
			(int) $config['maxMessageLen'],
			esc_attr__( 'Send message', 'site-walker' )
		);

		// Voluntary "Request an email back" CTA. Visible when the chat is
		// in normal mode (not terminated, not in email-entry); hidden by
		// the widget JS when entering the email flow or when the session
		// terminates. Default-visible so a visitor in plain chat mode sees
		// the option without waiting for any async wiring.
		$panel .= sprintf(
			'<div class="swwp-email-cta"><a href="#" class="swwp-email-cta-link">%s</a></div>',
			esc_html__( 'Request an email back', 'site-walker' )
		);

		// Email-capture area — unified container for the entry form, a
		// success/error message, and a back-to-chat link. Visibility of
		// each child is driven by the widget's state machine (showChatMode
		// / showEmailEntry / showEmailResult). Hidden by default; the
		// widget reveals it when entering the email flow (either via the
		// CTA above or via a session_terminated event).
		$panel .= '<div class="swwp-email-area" hidden>';
		$panel .= '<div class="swwp-email-message" hidden></div>';
		$panel .= sprintf(
			'<form class="swwp-email-row" autocomplete="email"><input type="email" class="swwp-email-input" placeholder="%1$s" required maxlength="255" /><button type="submit" class="swwp-email-send button" aria-label="%2$s">&rarr;</button></form>',
			esc_attr__( 'Your email address', 'site-walker' ),
			esc_attr__( 'Send email', 'site-walker' )
		);
		$panel .= sprintf(
			'<div class="swwp-email-back" hidden><a href="#" class="swwp-email-back-link">%s</a></div>',
			esc_html__( '← Back to chat', 'site-walker' )
		);
		$panel .= '</div>';

		$panel .= '</div>';

		echo $panel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All interpolated values escaped above.

		printf( '</div>' );
	}
}
