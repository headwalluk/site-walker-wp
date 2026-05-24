<?php
/**
 * Sessions tab — list + detail view for recent conversations.
 *
 * Both the list and detail containers live in this single panel; the admin
 * JS toggles between them based on the URL hash (`#sessions` for the list,
 * `#sessions/<id>` for the detail). Rendering happens entirely client-side
 * via REST calls; nothing about a session is mirrored to wp_options.
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

$has_slug = '' !== (string) get_option( OPT_CHATBOT_SLUG, '' );
?>
<div class="swwp-sessions-tab" data-tab="sessions">

	<div class="swwp-not-configured" <?php echo $has_slug ? 'hidden' : ''; ?>>
		<p>
			<?php esc_html_e( 'No chatbot connected yet. Save your admin key on the Connection tab first.', 'site-walker' ); ?>
		</p>
		<p>
			<a href="#connection" class="button button-secondary swwp-jump-to-connection">
				<?php esc_html_e( 'Go to Connection', 'site-walker' ); ?>
			</a>
		</p>
	</div>

	<div class="swwp-tab-loading" hidden>
		<p><?php esc_html_e( 'Loading…', 'site-walker' ); ?></p>
	</div>

	<!-- List view -->
	<div class="swwp-sessions-list" hidden>
		<div class="swwp-sessions-controls">
			<button type="button" class="button swwp-sessions-reload">
				<?php esc_html_e( 'Refresh', 'site-walker' ); ?>
			</button>
			<span class="swwp-status" role="status" aria-live="polite"></span>
		</div>

		<table class="widefat striped swwp-sessions-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'site-walker' ); ?></th>
					<th><?php esc_html_e( 'Last active', 'site-walker' ); ?></th>
					<th><?php esc_html_e( 'Messages', 'site-walker' ); ?></th>
					<th><?php esc_html_e( 'Tokens', 'site-walker' ); ?></th>
					<th><?php esc_html_e( 'Cost', 'site-walker' ); ?></th>
					<th><?php esc_html_e( 'Visitor', 'site-walker' ); ?></th>
					<th><?php esc_html_e( 'Status', 'site-walker' ); ?></th>
				</tr>
			</thead>
			<tbody class="swwp-sessions-rows">
				<!-- populated by JS -->
			</tbody>
		</table>

		<p class="swwp-sessions-empty" hidden>
			<?php esc_html_e( 'No sessions yet for this chatbot.', 'site-walker' ); ?>
		</p>

		<div class="swwp-sessions-pagination">
			<button type="button" class="button swwp-sessions-prev" disabled>
				<?php esc_html_e( '← Prev', 'site-walker' ); ?>
			</button>
			<span class="swwp-sessions-pageinfo"></span>
			<button type="button" class="button swwp-sessions-next" disabled>
				<?php esc_html_e( 'Next →', 'site-walker' ); ?>
			</button>
		</div>
	</div>

	<!-- Detail view -->
	<div class="swwp-session-detail" hidden>
		<p>
			<a href="#sessions" class="swwp-back-to-list">
				<?php esc_html_e( '← Back to list', 'site-walker' ); ?>
			</a>
		</p>

		<div class="swwp-session-summary">
			<!-- populated by JS -->
		</div>

		<div class="swwp-session-messages">
			<!-- populated by JS -->
		</div>
	</div>

</div>
