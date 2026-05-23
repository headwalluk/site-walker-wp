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
			'/' . $root . '/connection/slug',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'connection_slug_post' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'slug' => array( 'type' => 'string', 'required' => true ),
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
				'api_url'        => $api_url,
				'admin_key_set'  => '' !== $key,
				'admin_key_mask' => mask_admin_key( $key ),
				'chatbot_slug'   => $slug,
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

		// Auto-discover chatbots under the new key.
		$client = get_admin_api_client();
		if ( ! $client ) {
			return $this->error_response( 500, 'unexpected', array( 'message' => 'Stored key but could not build API client.' ) );
		}

		$result = $client->get( '/admin/chatbots' );
		if ( ! $result['ok'] ) {
			// Don't clear the key on auth failure — let the operator inspect
			// what they pasted by hitting the test-connection button.
			return $this->error_response( $result['status'], $result['error'], $result['detail'] );
		}

		$summarised = $this->summarise_chatbots( $result['data'] );

		if ( 1 === count( $summarised ) && '' !== $summarised[0]['slug'] ) {
			update_option( OPT_CHATBOT_SLUG, $summarised[0]['slug'], false );
		}

		return rest_ensure_response(
			array(
				'ok'           => true,
				'chatbots'     => $summarised,
				'chatbot_slug' => (string) get_option( OPT_CHATBOT_SLUG, '' ),
			)
		);
	}

	public function connection_delete( \WP_REST_Request $request ) {
		unset( $request );
		update_option( OPT_ADMIN_KEY, '', false );
		update_option( OPT_CHATBOT_SLUG, '', false );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public function connection_slug_post( \WP_REST_Request $request ) {
		$slug = is_string( $request['slug'] ) ? trim( $request['slug'] ) : '';
		if ( '' === $slug ) {
			return $this->error_response( 400, 'validation_failed', array( 'message' => 'Slug is required.' ) );
		}

		// Verify the slug exists under the current admin key before saving it.
		$client = get_admin_api_client();
		if ( ! $client ) {
			return $this->error_response( 412, 'not_configured', array( 'message' => 'Admin key not set.' ) );
		}

		$result = $client->get( '/admin/chatbots/' . rawurlencode( $slug ) );
		if ( ! $result['ok'] ) {
			return $this->error_response( $result['status'], $result['error'], $result['detail'] );
		}

		update_option( OPT_CHATBOT_SLUG, $slug, false );
		return rest_ensure_response( array( 'ok' => true, 'chatbot_slug' => $slug ) );
	}

	public function connection_test( \WP_REST_Request $request ) {
		unset( $request );
		$client = get_admin_api_client();
		if ( ! $client ) {
			return $this->error_response( 412, 'not_configured', array( 'message' => 'API URL and admin key must both be set.' ) );
		}

		$result = $client->get( '/admin/chatbots' );
		if ( ! $result['ok'] ) {
			return $this->error_response( $result['status'], $result['error'], $result['detail'] );
		}

		$summarised = $this->summarise_chatbots( $result['data'] );

		return rest_ensure_response(
			array(
				'ok'           => true,
				'chatbots'     => $summarised,
				'chatbot_slug' => (string) get_option( OPT_CHATBOT_SLUG, '' ),
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
	// Shared helpers
	// ---------------------------------------------------------------------

	/**
	 * Forward to /admin/chatbots/{slug}{suffix} using the stored slug.
	 */
	private function proxy_to_chatbot( string $method, ?array $body = null, string $suffix = '', array $query = array() ) {
		$client = get_admin_api_client();
		if ( ! $client ) {
			return $this->error_response( 412, 'not_configured', array( 'message' => 'Admin key not set. Configure it in the Connection tab.' ) );
		}

		$slug = (string) get_option( OPT_CHATBOT_SLUG, '' );
		if ( '' === $slug ) {
			return $this->error_response( 412, 'not_configured', array( 'message' => 'No chatbot selected. Configure it in the Connection tab.' ) );
		}

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
