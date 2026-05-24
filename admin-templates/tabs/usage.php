<?php
/**
 * Usage tab — read-only token + cost totals.
 *
 * GETs /admin/chatbots/{slug}/usage?since=... and renders the response.
 * Nothing here writes back to the API; this is purely for the operator to
 * see what their chatbot is actually spending.
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

$has_slug = '' !== (string) get_option( OPT_CHATBOT_SLUG, '' );

$since_choices = array(
	''    => __( 'All time', 'site-walker' ),
	'1h'  => __( 'Last hour', 'site-walker' ),
	'24h' => __( 'Last 24 hours', 'site-walker' ),
	'7d'  => __( 'Last 7 days', 'site-walker' ),
	'30d' => __( 'Last 30 days', 'site-walker' ),
);
?>
<div class="swwp-usage-tab" data-tab="usage">

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
		<p><?php esc_html_e( 'Loading usage…', 'site-walker' ); ?></p>
	</div>

	<div class="swwp-tab-form" hidden>
		<div class="swwp-usage-controls">
			<label for="swwp-usage-since"><?php esc_html_e( 'Since:', 'site-walker' ); ?></label>
			<select id="swwp-usage-since">
				<?php foreach ( $since_choices as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( '24h', $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button swwp-usage-reload">
				<?php esc_html_e( 'Refresh', 'site-walker' ); ?>
			</button>
			<span class="swwp-status" role="status" aria-live="polite"></span>
		</div>

		<div class="swwp-usage-warnings" hidden></div>

		<table class="widefat striped swwp-usage-table">
			<thead>
				<tr>
					<th></th>
					<th><?php esc_html_e( 'Combined', 'site-walker' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'site-walker' ); ?> <span class="swwp-muted" title="<?php esc_attr_e( 'Counts toward the daily-cap budget.', 'site-walker' ); ?>">ⓘ</span></th>
					<th><?php esc_html_e( 'Admin mode', 'site-walker' ); ?> <span class="swwp-muted" title="<?php esc_attr_e( 'Logged-in WP admin sessions; excluded from the daily-cap budget.', 'site-walker' ); ?>">ⓘ</span></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$rows = array(
					'message_count'         => __( 'Messages', 'site-walker' ),
					'cost_usd'              => __( 'Cost (USD)', 'site-walker' ),
					'tokens_in'             => __( 'Tokens — input', 'site-walker' ),
					'tokens_out'            => __( 'Tokens — output', 'site-walker' ),
					'cache_creation_tokens' => __( 'Tokens — cache write', 'site-walker' ),
					'cache_read_tokens'     => __( 'Tokens — cache read', 'site-walker' ),
				);
				foreach ( $rows as $field => $label ) :
					?>
					<tr>
						<th><?php echo esc_html( $label ); ?></th>
						<td class="swwp-usage-value" data-field="<?php echo esc_attr( $field ); ?>" data-segment="combined">—</td>
						<td class="swwp-usage-value" data-field="<?php echo esc_attr( $field ); ?>" data-segment="customer">—</td>
						<td class="swwp-usage-value" data-field="<?php echo esc_attr( $field ); ?>" data-segment="admin">—</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th><?php esc_html_e( 'Period', 'site-walker' ); ?></th>
					<td class="swwp-usage-period" colspan="3">—</td>
				</tr>
			</tbody>
		</table>
	</div>

</div>
