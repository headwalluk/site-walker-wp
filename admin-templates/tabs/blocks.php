<?php
/**
 * Context tab — create / edit / delete the chatbot's system context blocks.
 *
 * Upstream calls these "system blocks"; we surface them as "Context" so the
 * merchant isn't faced with internal terminology. Each block is a named
 * markdown file the upstream API stitches into the assistant's system prompt.
 * The list and editor both live in this single panel; the admin JS toggles
 * between them. Nothing is mirrored to wp_options — the list is fetched live
 * via REST on tab activation and each block's content on demand.
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

$has_slug = '' !== (string) get_option( OPT_CHATBOT_SLUG, '' );
?>
<div class="swwp-blocks-tab" data-tab="blocks">

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
	<div class="swwp-blocks-list" hidden>
		<p class="description swwp-blocks-intro">
			<?php esc_html_e( 'Context blocks are snippets of background knowledge the assistant gets with every conversation — store policies, shipping rules, tone reminders, anything it should always know. Add as many as you need.', 'site-walker' ); ?>
		</p>

		<div class="swwp-blocks-controls">
			<button type="button" class="button button-primary swwp-block-new">
				<?php esc_html_e( 'Add block', 'site-walker' ); ?>
			</button>
			<button type="button" class="button swwp-blocks-reload">
				<?php esc_html_e( 'Refresh', 'site-walker' ); ?>
			</button>
			<span class="swwp-status" role="status" aria-live="polite"></span>
		</div>

		<table class="widefat striped swwp-blocks-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'site-walker' ); ?></th>
					<th><?php esc_html_e( 'Size', 'site-walker' ); ?></th>
					<th><?php esc_html_e( 'Last modified', 'site-walker' ); ?></th>
				</tr>
			</thead>
			<tbody class="swwp-blocks-rows">
				<!-- populated by JS -->
			</tbody>
		</table>

		<p class="swwp-blocks-empty" hidden>
			<?php esc_html_e( 'No context blocks yet. Add one to give your assistant extra background to work from.', 'site-walker' ); ?>
		</p>
	</div>

	<!-- Editor view -->
	<div class="swwp-block-editor" hidden>
		<p>
			<a href="#blocks" class="swwp-back-to-list">
				<?php esc_html_e( '← Back to list', 'site-walker' ); ?>
			</a>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="swwp-block-name"><?php esc_html_e( 'Name', 'site-walker' ); ?></label>
				</th>
				<td>
					<input type="text" id="swwp-block-name" class="regular-text code" autocomplete="off" />
					<p class="description">
						<?php esc_html_e( 'Letters, numbers, hyphens and underscores only. The name can\'t be changed after the block is created.', 'site-walker' ); ?>
					</p>
					<p class="description swwp-block-name-hint">
						<?php esc_html_e( 'A few names are special: the assistant\'s persona is set on the Chatbot tab; HANDOFF_SOFT and HANDOFF_HARD let you customise the messages shown when a conversation is handed off.', 'site-walker' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="swwp-block-content"><?php esc_html_e( 'Content', 'site-walker' ); ?></label>
				</th>
				<td>
					<textarea id="swwp-block-content" class="large-text code" rows="16"></textarea>
					<p class="description">
						<span class="swwp-block-bytes"></span>
						<?php esc_html_e( 'Markdown is supported. Maximum 64 KB.', 'site-walker' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<div class="swwp-tab-save">
			<button type="button" class="button button-primary swwp-block-save">
				<?php esc_html_e( 'Save block', 'site-walker' ); ?>
			</button>
			<button type="button" class="button button-link-delete swwp-block-delete" hidden>
				<?php esc_html_e( 'Delete', 'site-walker' ); ?>
			</button>
			<span class="swwp-status" role="status" aria-live="polite"></span>
		</div>
	</div>

</div>
