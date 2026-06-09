<?php
/**
 * WP REST endpoints under /wp-json/site-walker/v1/admin/*.
 *
 * Server-side proxy layer between wp-admin (form JS) and the upstream
 * /admin/chatbots/* API. Each route:
 *   - Requires the manage_options capability.
 *   - Requires a valid X-WP-Nonce (WP REST default; checked by core when
 *     the request is treated as same-origin authenticated).
 *   - Constructs an Admin_API_Client from the stored connection options.
 *   - Forwards to the upstream API and returns the uniform envelope.
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

class Admin_REST {

	/**
	 * Register all M7 admin proxy routes. Called on `rest_api_init`.
	 */
	public function register_routes(): void {
		$ns   = ADMIN_REST_NAMESPACE;
		$root = ADMIN_REST_ROOT;

		// Connection — local state + auto-discover flow.
		register_rest_route(
			$ns,
			'/' . $root . '/connection',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'connection_get' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'connection_post' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'admin_key' => array( 'type' => 'string', 'required' => true ),
						'api_url'   => array( 'type' => 'string', 'required' => false ),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'connection_delete' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/' . $root . '/connection/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'connection_test' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		// Chatbot — welcome / persona / budgets.
		register_rest_route(
			$ns,
			'/' . $root . '/chatbot',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'chatbot_get' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'chatbot_patch' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		// Geo — mode + countries.
		register_rest_route(
			$ns,
			'/' . $root . '/chatbot/geo',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'geo_get' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'geo_patch' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		// Usage — read-only.
		register_rest_route(
			$ns,
			'/' . $root . '/chatbot/usage',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'usage_get' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'since' => array( 'type' => 'string', 'required' => false ),
				),
			)
		);

		// Context blocks — filesystem-backed system blocks. Surfaced in the
		// UI as the "Context" tab. List + delete ride the JSON proxy; the
		// single-block get/put use the raw-body path on Admin_API_Client
		// because block content is text/markdown, not JSON.
		register_rest_route(
			$ns,
			'/' . $root . '/chatbot/blocks',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'blocks_list' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			$ns,
			'/' . $root . '/chatbot/blocks/(?P<name>[A-Za-z0-9_-]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'block_get' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'name' => array( 'type' => 'string', 'required' => true ),
					),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'block_put' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'name' => array( 'type' => 'string', 'required' => true ),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'block_delete' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'name' => array( 'type' => 'string', 'required' => true ),
					),
				),
			)
		);

		// Sessions / messages — read-only browse over a chatbot's conversations.
		// Proxies the upstream M22 routes; the visitor's session token is
		// deliberately NOT in the response shape (sessions addressed by
		// integer id), so there's no hijack risk in surfacing this to admins.
		register_rest_route(
			$ns,
			'/' . $root . '/chatbot/sessions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'sessions_list' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'page'      => array( 'type' => 'integer', 'required' => false ),
					'page_size' => array( 'type' => 'integer', 'required' => false ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/' . $root . '/chatbot/sessions/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'session_get' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'id' => array( 'type' => 'integer', 'required' => true ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/' . $root . '/chatbot/sessions/(?P<id>\d+)/messages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'session_messages' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'id' => array( 'type' => 'integer', 'required' => true ),
				),
			)
		);

		// Admin-mode session mint — called by the front-end widget JS when a
		// logged-in admin user loads a page. NOT under the /admin/* prefix
		// because it's the only route in the namespace that's called from
		// the front end rather than wp-admin. Same manage_options + nonce
		// gate as the /admin/* routes; the upstream POST it forwards to
		// requires the account admin bearer key, which the WP host holds.
		register_rest_route(
			$ns,
			'/admin-session',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'admin_session_post' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Permission gate — manage_options + the REST cookie-nonce that core
	 * checks automatically for same-origin requests with X-WP-Nonce.
	 */
	public function can_manage(): bool {
		return current_user_can( ADMIN_CAPABILITY );
	}

	// ---------------------------------------------------------------------
	// Connection routes
	// ---------------------------------------------------------------------

	public function connection_get( \WP_REST_Request $request ) {
		unset( $request );
		$api_url = (string) get_option( OPT_API_URL, DEF_API_URL );
		$key     = (string) get_option( OPT_ADMIN_KEY, '' );
		$slug    = (string) get_option( OPT_CHATBOT_SLUG, '' );

		return rest_ensure_response(
			array(
				'api_url'         => $api_url,
				'admin_key_set'   => '' !== $key,
				'admin_key_mask'  => mask_admin_key( $key ),
				'chatbot_slug'    => $slug,
				'expected_origin' => get_site_origin(),
			)
		);
	}

	public function connection_post( \WP_REST_Request $request ) {
		$key     = is_string( $request['admin_key'] ) ? trim( $request['admin_key'] ) : '';
		$api_url = is_string( $request['api_url'] ?? null ) ? trim( $request['api_url'] ) : null;

		if ( ! preg_match( ADMIN_KEY_REGEX, $key ) ) {
			return $this->error_response( 400, 'validation_failed', array( 'message' => 'Admin key must look like sw_… followed by 43 base64url characters.' ) );
		}

		// Optionally update the API URL atomically with the key save.
		if ( null !== $api_url && '' !== $api_url ) {
			$clean_url = esc_url_raw( $api_url );
			if ( '' === $clean_url ) {
				return $this->error_response( 400, 'validation_failed', array( 'message' => 'API URL is not a valid URL.' ) );
			}
			update_option( OPT_API_URL, untrailingslashit( $clean_url ), false );
		}

		// Persist the key (autoload=no) and clear any stale slug — the new
		// key may be account-scoped to a different account or set of chatbots.
		update_option( OPT_ADMIN_KEY, $key, false );
		update_option( OPT_CHATBOT_SLUG, '', false );

		$client = get_admin_api_client();
		if ( ! $client ) {
			return $this->error_response( 500, 'unexpected', array( 'message' => 'Stored key but could not build API client.' ) );
		}

		// 1. Fetch every chatbot in the account so we can iterate their origin
		//    allowlists.
		$result = $client->get( '/admin/chatbots' );
		if ( ! $result['ok'] ) {
			// Don't clear the key on auth failure — let the operator inspect
			// what they pasted by hitting the test-connection button.
			return $this->error_response( $result['status'], $result['error'], $result['detail'] );
		}

		$chatbots = $this->summarise_chatbots( $result['data'] );
		$origin   = get_site_origin();

		if ( '' === $origin ) {
			return $this->error_response( 500, 'unexpected', array( 'message' => 'Could not determine this site\'s origin from site_url().' ) );
		}

		// 2. For each chatbot, fetch origins and check whether this site's
		//    origin is on its allowlist.
		$matches = $this->match_chatbots_to_origin( $client, $chatbots, $origin );

		// 3. Zero-match path — clearest user-facing error of the new flow.
		//    Surface the full chatbot list so the operator can see what they
		//    might need to add an origin to.
		if ( 0 === count( $matches ) ) {
			return $this->error_response(
				404,
				'no_origin_match',
				array(
					'message'            => sprintf(
						"No chatbot in this account has %s on its origin allowlist.",
						$origin
					),
					'expected_origin'    => $origin,
					'available_chatbots' => $chatbots,
				)
			);
		}

		// 4. Single (or first-of-multiple) match. Multiple is unexpected
		//    because origin uniqueness is enforced upstream, but we handle it
		//    defensively and surface the list so the operator notices.
		$picked = $matches[0]['slug'];
		update_option( OPT_CHATBOT_SLUG, $picked, false );

		return rest_ensure_response(
			array(
				'ok'              => true,
				'chatbot_slug'    => $picked,
				'chatbot_name'    => $matches[0]['name'],
				'expected_origin' => $origin,
				'match_count'     => count( $matches ),
				'matches'         => $matches, // operator-facing: surface ambiguity if any
			)
		);
	}

	public function connection_delete( \WP_REST_Request $request ) {
		unset( $request );
		update_option( OPT_ADMIN_KEY, '', false );
		update_option( OPT_CHATBOT_SLUG, '', false );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public function connection_test( \WP_REST_Request $request ) {
		unset( $request );
		$client = get_admin_api_client();
		if ( ! $client ) {
			return $this->error_response( 412, 'not_configured', array( 'message' => 'API URL and admin key must both be set.' ) );
		}

		$slug   = (string) get_option( OPT_CHATBOT_SLUG, '' );
		$origin = get_site_origin();

		// If a slug is saved, verify (a) the chatbot still exists under this
		// key, and (b) this site's origin is still on its allowlist.
		if ( '' !== $slug ) {
			$origins_result = $client->get( '/admin/chatbots/' . rawurlencode( $slug ) . '/origins' );
			if ( ! $origins_result['ok'] ) {
				return $this->error_response( $origins_result['status'], $origins_result['error'], $origins_result['detail'] );
			}

			$origin_match = $this->origins_include( $origins_result['data'], $origin );

			return rest_ensure_response(
				array(
					'ok'              => true,
					'chatbot_slug'    => $slug,
					'expected_origin' => $origin,
					'origin_match'    => $origin_match,
				)
			);
		}

		// No slug saved — just confirm the key authenticates by listing chatbots.
		$result = $client->get( '/admin/chatbots' );
		if ( ! $result['ok'] ) {
			return $this->error_response( $result['status'], $result['error'], $result['detail'] );
		}

		return rest_ensure_response(
			array(
				'ok'              => true,
				'chatbot_slug'    => '',
				'expected_origin' => $origin,
				'origin_match'    => false,
			)
		);
	}

	// ---------------------------------------------------------------------
	// Chatbot routes (welcome / persona / budgets)
	// ---------------------------------------------------------------------

	public function chatbot_get( \WP_REST_Request $request ) {
		unset( $request );
		return $this->proxy_to_chatbot( 'GET' );
	}

	public function chatbot_patch( \WP_REST_Request $request ) {
		$body = $this->whitelist_patch_body( $request->get_json_params() ?: array(), array(
			'name', 'welcome_message', 'persona',
			'daily_budget_usd', 'session_budget_usd', 'handoff_threshold_pct',
			// M10 — operational availability + admin-mode session cap.
			'timezone', 'availability', 'admin_session_budget_usd',
		) );
		return $this->proxy_to_chatbot( 'PATCH', $body );
	}

	// ---------------------------------------------------------------------
	// Geo routes
	// ---------------------------------------------------------------------

	public function geo_get( \WP_REST_Request $request ) {
		unset( $request );
		return $this->proxy_to_chatbot( 'GET', null, '/geo' );
	}

	public function geo_patch( \WP_REST_Request $request ) {
		$body = $this->whitelist_patch_body( $request->get_json_params() ?: array(), array( 'mode', 'countries' ) );
		return $this->proxy_to_chatbot( 'PATCH', $body, '/geo' );
	}

	// ---------------------------------------------------------------------
	// Usage route
	// ---------------------------------------------------------------------

	public function usage_get( \WP_REST_Request $request ) {
		$since = is_string( $request['since'] ?? null ) ? (string) $request['since'] : '';
		$query = '' === $since ? array() : array( 'since' => $since );
		return $this->proxy_to_chatbot( 'GET', null, '/usage', $query );
	}

	// ---------------------------------------------------------------------
	// Context blocks (filesystem-backed system blocks)
	// ---------------------------------------------------------------------

	/**
	 * List the chatbot's blocks. Upstream returns `{blocks:[{name,size}]}`,
	 * which is JSON, so this rides the standard JSON proxy.
	 */
	public function blocks_list( \WP_REST_Request $request ) {
		unset( $request );
		return $this->proxy_to_chatbot( 'GET', null, '/blocks' );
	}

	/**
	 * Fetch one block's content. Upstream responds with raw `text/markdown`,
	 * so we use the raw client path and re-wrap the body into a small JSON
	 * envelope (`{name, content}`) for the admin JS.
	 */
	public function block_get( \WP_REST_Request $request ) {
		$name = (string) $request['name'];
		$err  = $this->validate_block_name( $name );
		if ( null !== $err ) {
			return $err;
		}

		$ctx = $this->resolve_client_and_slug();
		if ( isset( $ctx['error'] ) ) {
			return $ctx['error'];
		}

		$path   = '/admin/chatbots/' . rawurlencode( $ctx['slug'] ) . '/blocks/' . rawurlencode( $name );
		$result = $ctx['client']->get_raw( $path );
		if ( ! $result['ok'] ) {
			return $this->error_response( $result['status'], $result['error'], $result['detail'] );
		}

		return rest_ensure_response(
			array(
				'name'    => $name,
				'content' => (string) $result['data'],
			)
		);
	}

	/**
	 * Write/overwrite a block. Takes `{content}` JSON from the browser and
	 * forwards it as a `text/markdown` body upstream. Enforces the name rules
	 * and 64KB cap before the round-trip so the operator gets a friendly
	 * error rather than a raw upstream 400/413.
	 */
	public function block_put( \WP_REST_Request $request ) {
		$name = (string) $request['name'];
		$err  = $this->validate_block_name( $name );
		if ( null !== $err ) {
			return $err;
		}

		$body    = $request->get_json_params();
		$content = is_array( $body ) && isset( $body['content'] ) && is_string( $body['content'] ) ? $body['content'] : null;
		if ( null === $content ) {
			return $this->error_response( 400, 'validation_failed', array( 'message' => 'Block content is required.' ) );
		}
		if ( strlen( $content ) > BLOCK_MAX_BYTES ) {
			return $this->error_response( 413, 'validation_failed', array( 'message' => 'Block content exceeds the 64 KB limit.' ) );
		}

		$ctx = $this->resolve_client_and_slug();
		if ( isset( $ctx['error'] ) ) {
			return $ctx['error'];
		}

		$path   = '/admin/chatbots/' . rawurlencode( $ctx['slug'] ) . '/blocks/' . rawurlencode( $name );
		$result = $ctx['client']->put_raw( $path, $content, 'text/markdown' );
		if ( ! $result['ok'] ) {
			return $this->error_response( $result['status'], $result['error'], $result['detail'] );
		}

		return rest_ensure_response(
			array(
				'ok'   => true,
				'name' => $name,
			)
		);
	}

	/**
	 * Delete a block. Upstream returns 204 (empty body); the JSON delete()
	 * path handles that fine (data is null, ok is true).
	 */
	public function block_delete( \WP_REST_Request $request ) {
		$name = (string) $request['name'];
		$err  = $this->validate_block_name( $name );
		if ( null !== $err ) {
			return $err;
		}

		$ctx = $this->resolve_client_and_slug();
		if ( isset( $ctx['error'] ) ) {
			return $ctx['error'];
		}

		$path   = '/admin/chatbots/' . rawurlencode( $ctx['slug'] ) . '/blocks/' . rawurlencode( $name );
		$result = $ctx['client']->delete( $path );
		if ( ! $result['ok'] ) {
			return $this->error_response( $result['status'], $result['error'], $result['detail'] );
		}

		return rest_ensure_response( array( 'ok' => true ) );
	}

	// ---------------------------------------------------------------------
	// Sessions / messages — M22 review surface
	// ---------------------------------------------------------------------

	public function sessions_list( \WP_REST_Request $request ) {
		$page      = (int) ( $request['page'] ?? 0 );
		$page_size = (int) ( $request['page_size'] ?? 0 );

		$query = array();
		if ( $page > 0 ) {
			$query['page'] = $page;
		}
		if ( $page_size > 0 ) {
			$query['page_size'] = $page_size;
		}

		return $this->proxy_to_chatbot( 'GET', null, '/sessions', $query );
	}

	public function session_get( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		return $this->proxy_to_chatbot( 'GET', null, '/sessions/' . $id );
	}

	public function session_messages( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		return $this->proxy_to_chatbot( 'GET', null, '/sessions/' . $id . '/messages' );
	}

	// ---------------------------------------------------------------------
	// Admin-mode session mint (M8)
	// ---------------------------------------------------------------------

	/**
	 * Mint an admin-mode session via upstream `POST /admin/chatbots/{slug}/sessions`
	 * and relay the envelope back to the front-end widget JS.
	 *
	 * The capability gate (manage_options) is in `can_manage()`; this method
	 * runs only after that's passed. We never touch or expose the account
	 * admin key on the wire to the browser — it's resident on the WP host
	 * and used here to make the server-to-server call.
	 */
	public function admin_session_post( \WP_REST_Request $request ) {
		unset( $request );

		$client = get_admin_api_client();
		if ( ! $client ) {
			return $this->error_response( 412, 'not_configured', array( 'message' => 'Admin key not set. Configure it in WP Settings > Site Walker > Connection.' ) );
		}

		$slug = (string) get_option( OPT_CHATBOT_SLUG, '' );
		if ( '' === $slug ) {
			return $this->error_response( 412, 'not_configured', array( 'message' => 'No chatbot selected. Configure it in WP Settings > Site Walker > Connection.' ) );
		}

		$result = $client->post( '/admin/chatbots/' . rawurlencode( $slug ) . '/sessions', array() );
		if ( ! $result['ok'] ) {
			return $this->error_response( $result['status'], $result['error'], $result['detail'] );
		}

		// Upstream returns { session_token, welcome_message, is_admin_mode: true }.
		// Pass it straight through; the widget JS treats this exactly like a
		// regular `POST /sessions` response with the extra is_admin_mode flag.
		return rest_ensure_response( $result['data'] );
	}

	// ---------------------------------------------------------------------
	// Shared helpers
	// ---------------------------------------------------------------------

	/**
	 * Resolve the API client + stored chatbot slug, or an error response if
	 * either is missing. Returns either `[ 'error' => WP_REST_Response ]` or
	 * `[ 'client' => Admin_API_Client, 'slug' => string ]`.
	 *
	 * @return array<string,mixed>
	 */
	private function resolve_client_and_slug(): array {
		$client = get_admin_api_client();
		if ( ! $client ) {
			return array( 'error' => $this->error_response( 412, 'not_configured', array( 'message' => 'Admin key not set. Configure it in the Connection tab.' ) ) );
		}

		$slug = (string) get_option( OPT_CHATBOT_SLUG, '' );
		if ( '' === $slug ) {
			return array( 'error' => $this->error_response( 412, 'not_configured', array( 'message' => 'No chatbot selected. Configure it in the Connection tab.' ) ) );
		}

		return array(
			'client' => $client,
			'slug'   => $slug,
		);
	}

	/**
	 * Validate a block name against the upstream pattern + reserved list.
	 * Returns null when valid, or a ready-to-return error response.
	 *
	 * @return \WP_REST_Response|null
	 */
	private function validate_block_name( string $name ) {
		if ( ! preg_match( BLOCK_NAME_REGEX, $name ) ) {
			return $this->error_response( 400, 'validation_failed', array( 'message' => 'Block name may only contain letters, numbers, hyphens and underscores.' ) );
		}
		if ( in_array( $name, RESERVED_BLOCK_NAMES, true ) ) {
			return $this->error_response(
				400,
				'validation_failed',
				array(
					/* translators: %s: reserved block name like PERSONA */
					'message' => sprintf( '"%s" is a reserved name and can\'t be edited here.', $name ),
				)
			);
		}
		return null;
	}

	/**
	 * Forward to /admin/chatbots/{slug}{suffix} using the stored slug.
	 */
	private function proxy_to_chatbot( string $method, ?array $body = null, string $suffix = '', array $query = array() ) {
		$ctx = $this->resolve_client_and_slug();
		if ( isset( $ctx['error'] ) ) {
			return $ctx['error'];
		}
		$client = $ctx['client'];
		$slug   = $ctx['slug'];

		$path = '/admin/chatbots/' . rawurlencode( $slug ) . $suffix;

		switch ( $method ) {
			case 'GET':
				$result = $client->get( $path, $query );
				break;
			case 'PATCH':
				$result = $client->patch( $path, $body ?: array() );
				break;
			default:
				return $this->error_response( 500, 'unexpected', array( 'message' => 'Unsupported proxy method: ' . $method ) );
		}

		if ( ! $result['ok'] ) {
			return $this->error_response( $result['status'], $result['error'], $result['detail'] );
		}

		return rest_ensure_response( $result['data'] );
	}

	/**
	 * For each chatbot in `$chatbots`, fetch its origin allowlist and keep
	 * only the chatbots whose allowlist contains `$site_origin`. Returns the
	 * subset in the same `[ {slug, name}, ... ]` shape `summarise_chatbots`
	 * produces.
	 *
	 * Per-chatbot fetch failures (a single chatbot's /origins call returning
	 * an error) are silently skipped — that chatbot is simply not considered
	 * a match. The alternative (abort the whole match) would leave the
	 * operator with a worse error than "no chatbot matches" when one
	 * specific chatbot has a transient upstream problem.
	 *
	 * @param Admin_API_Client                                $client
	 * @param list<array{slug:string,name:string}>            $chatbots
	 * @param string                                          $site_origin
	 *
	 * @return list<array{slug:string,name:string}>
	 */
	private function match_chatbots_to_origin( Admin_API_Client $client, array $chatbots, string $site_origin ): array {
		$matches = array();
		foreach ( $chatbots as $bot ) {
			if ( empty( $bot['slug'] ) ) {
				continue;
			}
			$result = $client->get( '/admin/chatbots/' . rawurlencode( $bot['slug'] ) . '/origins' );
			if ( ! $result['ok'] ) {
				continue;
			}
			if ( $this->origins_include( $result['data'], $site_origin ) ) {
				$matches[] = $bot;
			}
		}
		return $matches;
	}

	/**
	 * Does the upstream `/origins` payload contain `$site_origin`? Upstream
	 * envelope is `{origins: [{id, chatbot_id, origin, created_at}, ...]}`.
	 * Defends against missing / malformed shapes so a single bad payload
	 * doesn't crash the matcher.
	 *
	 * @param mixed  $payload
	 * @param string $site_origin
	 */
	private function origins_include( $payload, string $site_origin ): bool {
		if ( '' === $site_origin ) {
			return false;
		}
		$list = is_array( $payload ) && isset( $payload['origins'] ) && is_array( $payload['origins'] )
			? $payload['origins']
			: array();
		foreach ( $list as $entry ) {
			if ( is_array( $entry ) && isset( $entry['origin'] ) && (string) $entry['origin'] === $site_origin ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Reduce the upstream `GET /admin/chatbots` response down to the
	 * `[ ['slug' => ..., 'name' => ...], ... ]` list the admin UI needs.
	 *
	 * The upstream response is `{ "chatbots": [ { slug, name, ... }, ... ] }`
	 * (see api-admin.md). We defend against the bare-array shape too in
	 * case that wrapper ever changes — and fall back to an empty list on
	 * anything weirder so the UI never crashes on an unexpected payload.
	 *
	 * @param mixed $payload Decoded JSON body from the upstream response.
	 *
	 * @return list<array{slug:string,name:string}>
	 */
	private function summarise_chatbots( $payload ): array {
		$list = is_array( $payload ) && isset( $payload['chatbots'] ) && is_array( $payload['chatbots'] )
			? $payload['chatbots']
			: array();

		return array_values(
			array_map(
				static function ( $chatbot ) {
					$slug = is_array( $chatbot ) && isset( $chatbot['slug'] ) ? (string) $chatbot['slug'] : '';
					$name = is_array( $chatbot ) && isset( $chatbot['name'] ) ? (string) $chatbot['name'] : '';
					return array( 'slug' => $slug, 'name' => $name );
				},
				$list
			)
		);
	}

	/**
	 * Keep only whitelisted keys from a PATCH body. Drops anything else so
	 * a malicious / curious admin can't piggy-back un-exposed fields (e.g.
	 * model_slug) through the proxy.
	 *
	 * @param array<string,mixed> $body
	 * @param string[]            $allowed
	 *
	 * @return array<string,mixed>
	 */
	private function whitelist_patch_body( array $body, array $allowed ): array {
		$out = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $body ) ) {
				$out[ $key ] = $body[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Build a uniform-shape error response for the admin JS to render.
	 */
	private function error_response( int $status, string $error, $detail = null ): \WP_REST_Response {
		$body = array( 'error' => $error );
		if ( null !== $detail ) {
			$body['detail'] = $detail;
		}
		// Keep the underlying upstream status so JS can branch on 401 / 404 /
		// 409 / 413 if it wants, but the response itself is always sent as 200
		// + an error envelope, so fetch() callers don't have to inspect both.
		$body['status'] = $status;
		return new \WP_REST_Response( $body, 200 );
	}
}
