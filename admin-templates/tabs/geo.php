<?php
/**
 * Geo tab — mode (allowall / blocklist / allowlist) + country list.
 *
 * v1 ships a plain textarea for the country list (comma-or-newline separated
 * ISO 3166-1 alpha-2 codes). A chip-input / autocomplete picker is on the
 * v2 list — see [[admin-area-extension]] open questions.
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

$has_slug = '' !== (string) get_option( OPT_CHATBOT_SLUG, '' );
?>
<div class="swwp-geo-tab" data-tab="geo">

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
		<p><?php esc_html_e( 'Loading geo policy…', 'site-walker' ); ?></p>
	</div>

	<div class="swwp-tab-form" hidden>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Mode', 'site-walker' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="swwp-geo-mode" value="allowall" data-field="mode" />
							<?php esc_html_e( 'Allow all visitors', 'site-walker' ); ?>
						</label><br />
						<label>
							<input type="radio" name="swwp-geo-mode" value="blocklist" data-field="mode" />
							<?php esc_html_e( 'Block listed countries (allow everyone else)', 'site-walker' ); ?>
						</label><br />
						<label>
							<input type="radio" name="swwp-geo-mode" value="allowlist" data-field="mode" />
							<?php esc_html_e( 'Allow only listed countries (block everyone else)', 'site-walker' ); ?>
						</label>
					</fieldset>
					<p class="description">
						<?php esc_html_e( 'Requires the upstream API server to have GEOIP_DB_PATH configured. Without it, only "allow all" is enforceable; the API will refuse to start a chat that needs an IP lookup.', 'site-walker' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="swwp-geo-countries"><?php esc_html_e( 'Country list', 'site-walker' ); ?></label>
				</th>
				<td>
					<textarea id="swwp-geo-countries" class="large-text code" rows="4" data-field="countries" placeholder="US, CA, GB"></textarea>
					<p class="description">
						<?php esc_html_e( 'ISO 3166-1 alpha-2 codes (two letters each), separated by commas, spaces, or newlines. Ignored when mode is "Allow all".', 'site-walker' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<div class="swwp-tab-save">
			<button type="button" class="button button-primary swwp-geo-save">
				<?php esc_html_e( 'Save geo policy', 'site-walker' ); ?>
			</button>
			<button type="button" class="button swwp-geo-reload">
				<?php esc_html_e( 'Reload from API', 'site-walker' ); ?>
			</button>
			<span class="swwp-status" role="status" aria-live="polite"></span>
		</div>
	</div>

</div>
