<?php
/**
 * Connection tab — API URL, admin key, auto-discovered chatbot slug.
 *
 * Server-renders the initial values, then admin.js drives all saves via the
 * REST endpoints under /wp-json/site-walker/v1/admin/connection*. Each value
 * is stored in wp_options, never in the browser beyond the in-flight form.
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

$api_url      = (string) get_option( OPT_API_URL, DEF_API_URL );
$admin_key    = (string) get_option( OPT_ADMIN_KEY, '' );
$chatbot_slug = (string) get_option( OPT_CHATBOT_SLUG, '' );
$key_masked   = mask_admin_key( $admin_key );
$key_is_set   = '' !== $admin_key;

?>
<div class="swwp-connection">

	<h2><?php esc_html_e( 'API server', 'site-walker' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Base URL of the Site Walker API instance this WordPress site talks to. Changing the URL clears the saved admin key, since keys are scoped to a specific API instance.', 'site-walker' ); ?>
	</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="swwp-api-url"><?php esc_html_e( 'API URL', 'site-walker' ); ?></label>
			</th>
			<td>
				<input type="url" id="swwp-api-url" class="regular-text code" value="<?php echo esc_attr( $api_url ); ?>" placeholder="<?php echo esc_attr( DEF_API_URL ); ?>" />
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Admin key', 'site-walker' ); ?></h2>
	<p class="description">
		<?php
		printf(
			/* translators: %s: literal command */
			esc_html__( 'Account admin key minted via %s. The key is stored on the WordPress host and never exposed to a visitor\'s browser; only the prefix and last 4 characters are shown back to you after save.', 'site-walker' ),
			'<code>./bin/sw account add-admin-key</code>'
		);
		?>
	</p>
	<table class="form-table" role="presentation">
		<tr class="swwp-key-row" data-state="<?php echo $key_is_set ? 'saved' : 'empty'; ?>">
			<th scope="row">
				<label for="swwp-admin-key"><?php esc_html_e( 'Admin key', 'site-walker' ); ?></label>
			</th>
			<td>
				<?php if ( $key_is_set ) : ?>
					<code class="swwp-key-mask"><?php echo esc_html( $key_masked ); ?></code>
					<button type="button" class="button swwp-key-clear">
						<?php esc_html_e( 'Clear', 'site-walker' ); ?>
					</button>
					<button type="button" class="button button-secondary swwp-key-replace">
						<?php esc_html_e( 'Replace', 'site-walker' ); ?>
					</button>
				<?php endif; ?>
				<input
					type="password"
					id="swwp-admin-key"
					class="regular-text code swwp-key-input"
					placeholder="sw_…"
					autocomplete="off"
					<?php echo $key_is_set ? 'hidden' : ''; ?>
				/>
				<button type="button" class="button button-primary swwp-key-save" <?php echo $key_is_set ? 'hidden' : ''; ?>>
					<?php esc_html_e( 'Save & connect', 'site-walker' ); ?>
				</button>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Chatbot', 'site-walker' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Auto-discovered after the admin key is saved. If the account has more than one chatbot, you\'ll be asked to pick which one this WordPress install manages.', 'site-walker' ); ?>
	</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Active chatbot', 'site-walker' ); ?></th>
			<td>
				<span class="swwp-active-chatbot" data-slug="<?php echo esc_attr( $chatbot_slug ); ?>">
					<?php
					if ( '' !== $chatbot_slug ) {
						echo '<code>' . esc_html( $chatbot_slug ) . '</code>';
					} else {
						esc_html_e( '— not connected —', 'site-walker' );
					}
					?>
				</span>
				<button type="button" class="button swwp-chatbot-picker-toggle" <?php echo '' === $admin_key ? 'hidden' : ''; ?>>
					<?php esc_html_e( 'Change…', 'site-walker' ); ?>
				</button>
				<div class="swwp-chatbot-picker" hidden>
					<select class="swwp-chatbot-select"></select>
					<button type="button" class="button button-primary swwp-chatbot-save">
						<?php esc_html_e( 'Use this chatbot', 'site-walker' ); ?>
					</button>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Connection', 'site-walker' ); ?></th>
			<td>
				<button type="button" class="button swwp-connection-test" <?php echo '' === $admin_key ? 'hidden' : ''; ?>>
					<?php esc_html_e( 'Test connection', 'site-walker' ); ?>
				</button>
				<span class="swwp-status" role="status" aria-live="polite"></span>
			</td>
		</tr>
	</table>

</div>
