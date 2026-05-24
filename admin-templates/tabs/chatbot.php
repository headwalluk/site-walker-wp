<?php
/**
 * Chatbot tab — welcome message, persona, budget caps, handoff threshold.
 *
 * All fields live upstream. The tab fetches GET /admin/chatbot on first
 * activation and PATCHes on save; no values are mirrored to wp_options.
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

$has_slug = '' !== (string) get_option( OPT_CHATBOT_SLUG, '' );
?>
<div class="swwp-chatbot-tab" data-tab="chatbot">

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
		<p><?php esc_html_e( 'Loading current chatbot configuration…', 'site-walker' ); ?></p>
	</div>

	<div class="swwp-tab-form" hidden>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="swwp-chatbot-welcome"><?php esc_html_e( 'Welcome message', 'site-walker' ); ?></label>
				</th>
				<td>
					<textarea id="swwp-chatbot-welcome" class="large-text" rows="2" data-field="welcome_message"></textarea>
					<p class="description"><?php esc_html_e( 'Shown to a visitor as the assistant\'s first message when they start a new session. Leave blank for no welcome.', 'site-walker' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="swwp-chatbot-persona"><?php esc_html_e( 'Persona', 'site-walker' ); ?></label>
				</th>
				<td>
					<textarea id="swwp-chatbot-persona" class="large-text" rows="6" data-field="persona"></textarea>
					<p class="description"><?php esc_html_e( 'High-level "who is the assistant" prompt prepended to every conversation. Tone, voice, what topics are in / out of scope. Leave blank to use the upstream default.', 'site-walker' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="swwp-chatbot-daily-budget"><?php esc_html_e( 'Daily budget cap (USD)', 'site-walker' ); ?></label>
				</th>
				<td>
					<input type="number" id="swwp-chatbot-daily-budget" min="0" step="0.01" class="small-text" data-field="daily_budget_usd" />
					<p class="description"><?php esc_html_e( 'Spend ceiling per UTC day. The widget hides itself for the rest of the day once this is hit (resets at next UTC midnight). Leave blank for no cap (upstream may still enforce SW_MAX_DAILY_BUDGET_USD).', 'site-walker' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="swwp-chatbot-session-budget"><?php esc_html_e( 'Session budget cap (USD)', 'site-walker' ); ?></label>
				</th>
				<td>
					<input type="number" id="swwp-chatbot-session-budget" min="0" step="0.01" class="small-text" data-field="session_budget_usd" />
					<p class="description"><?php esc_html_e( 'Spend ceiling per visitor conversation. When hit, the assistant\'s last reply is returned with session_terminated and the widget locks the input. Leave blank for no cap.', 'site-walker' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="swwp-chatbot-handoff-threshold"><?php esc_html_e( 'Soft handoff threshold (%)', 'site-walker' ); ?></label>
				</th>
				<td>
					<input type="number" id="swwp-chatbot-handoff-threshold" min="1" max="100" step="1" class="small-text" data-field="handoff_threshold_pct" />
					<span><?php esc_html_e( '%', 'site-walker' ); ?></span>
					<p class="description"><?php esc_html_e( 'Percent of the session budget at which the assistant is nudged to ask for the visitor\'s email (soft handoff). Default 80. Only meaningful when a session budget cap is set.', 'site-walker' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="swwp-chatbot-admin-session-budget"><?php esc_html_e( 'Admin-mode session budget cap (USD)', 'site-walker' ); ?></label>
				</th>
				<td>
					<input type="number" id="swwp-chatbot-admin-session-budget" min="0" step="0.01" class="small-text" data-field="admin_session_budget_usd" />
					<p class="description"><?php esc_html_e( 'Separate per-conversation cap for admin-mode sessions (logged-in WP admins). Leave blank for unbounded — admins are trusted by default.', 'site-walker' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="swwp-chatbot-tz"><?php esc_html_e( 'Timezone', 'site-walker' ); ?></label>
				</th>
				<td>
					<input type="text" id="swwp-chatbot-tz" class="regular-text code" placeholder="Europe/London" data-field="timezone" />
					<button type="button" class="button swwp-tz-use-site" hidden>
						<?php esc_html_e( 'Use this site\'s timezone', 'site-walker' ); ?>
					</button>
					<p class="description"><?php esc_html_e( 'IANA timezone identifier like Europe/London or America/New_York. Leave blank for UTC. Required if you want operational hours to mean local time.', 'site-walker' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Operational hours', 'site-walker' ); ?></th>
				<td>
					<fieldset class="swwp-availability-mode">
						<label>
							<input type="radio" name="swwp-availability-mode" value="always" />
							<?php esc_html_e( 'Always open', 'site-walker' ); ?>
						</label>
						&nbsp;&nbsp;
						<label>
							<input type="radio" name="swwp-availability-mode" value="schedule" />
							<?php esc_html_e( 'Per schedule', 'site-walker' ); ?>
						</label>
					</fieldset>
					<div class="swwp-availability-grid" hidden>
						<!-- populated by JS -->
					</div>
					<p class="description"><?php esc_html_e( 'Visitors landing outside the open windows see "We\'re closed until …"; sessions already in progress run past closing time. Use HH:MM 24-hour format; 24:00 is end-of-day. Times are in the chatbot\'s timezone (above).', 'site-walker' ); ?></p>
				</td>
			</tr>
		</table>

		<div class="swwp-tab-save">
			<button type="button" class="button button-primary swwp-chatbot-save">
				<?php esc_html_e( 'Save changes', 'site-walker' ); ?>
			</button>
			<button type="button" class="button swwp-chatbot-reload">
				<?php esc_html_e( 'Reload from API', 'site-walker' ); ?>
			</button>
			<span class="swwp-status" role="status" aria-live="polite"></span>
		</div>
	</div>

</div>
