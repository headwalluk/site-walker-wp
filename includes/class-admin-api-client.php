<?php
/**
 * Thin PHP wrapper around the upstream `/admin/chatbots/*` HTTP API.
 *
 * Server-side proxy — the account admin key never reaches the browser.
 * Used by Admin_REST (per-tab save handlers + auto-discover) and by the
 * Connection tab's render path (read current state on page load).
 *
 * @package Site_Walker
 */

namespace Site_Walker;

defined( 'ABSPATH' ) || die();

class Admin_API_Client {

	/**
	 * API base URL (no trailing slash).
	 */
	private string $base_url;

	/**
	 * Account admin bearer (sw_… ~46 chars).
	 */
	private string $bearer;

	public function __construct( string $base_url, string $bearer ) {
		$this->base_url = untrailingslashit( $base_url );
		$this->bearer   = $bearer;
	}

	/**
	 * Is the client configured well enough to make a request?
	 */
	public function is_configured(): bool {
		return '' !== $this->base_url && '' !== $this->bearer && filter_var( $this->base_url, FILTER_VALIDATE_URL );
	}

	public function get( string $path, array $query = array() ): array {
		return $this->request( 'GET', $path, null, $query );
	}

	public function post( string $path, array $body = array() ): array {
		return $this->request( 'POST', $path, $body );
	}

	public function patch( string $path, array $body = array() ): array {
		return $this->request( 'PATCH', $path, $body );
	}

	public function delete( string $path ): array {
		return $this->request( 'DELETE', $path, null );
	}

	/**
	 * One HTTP round-trip against the upstream admin surface.
	 *
	 * Returns a uniform envelope so callers don't have to special-case the
	 * WP_Error / response-array divide. Shape:
	 *   - on success: [ 'ok' => true,  'status' => int, 'data' => mixed ]
	 *   - on failure: [ 'ok' => false, 'status' => int, 'error' => string, 'detail' => array|null ]
	 *
	 * Status 0 means the request never left the WP host (DNS, timeout, etc.).
	 *
	 * @param string             $method GET / POST / PATCH / DELETE.
	 * @param string             $path   API path beginning with /.
	 * @param array<string,mixed>|null $body  JSON body for write methods.
	 * @param array<string,mixed>      $query Query-string params for read methods.
	 *
	 * @return array<string,mixed>
	 */
	private function request( string $method, string $path, ?array $body = null, array $query = array() ): array {
		$url = $this->base_url . $path;
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->bearer,
				'Accept'        => 'application/json',
				'User-Agent'    => 'Site-Walker-WP/' . \STWLK_PLUGIN_VERSION . '; ' . home_url(),
			),
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'     => false,
				'status' => 0,
				'error'  => 'transport_error',
				'detail' => array( 'message' => $response->get_error_message() ),
			);
		}

		$status   = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = (string) wp_remote_retrieve_body( $response );
		$decoded  = '' === $raw_body ? null : json_decode( $raw_body, true );

		if ( $status >= 200 && $status < 300 ) {
			return array(
				'ok'     => true,
				'status' => $status,
				'data'   => $decoded,
			);
		}

		// Non-2xx. The upstream API always uses { error, detail? } on failures,
		// but we defend against the off-script response (HTML error page from a
		// reverse proxy, empty body, malformed JSON) so the admin UI never has
		// to inspect a null.
		$error  = is_array( $decoded ) && isset( $decoded['error'] ) && is_string( $decoded['error'] )
			? $decoded['error']
			: 'unknown_error';
		$detail = is_array( $decoded ) && isset( $decoded['detail'] ) && is_array( $decoded['detail'] )
			? $decoded['detail']
			: null;

		return array(
			'ok'     => false,
			'status' => $status,
			'error'  => $error,
			'detail' => $detail,
		);
	}
}
