<?php

/**
 * REST proxy for Partshub OE lookup (keeps API key server-side).
 *
 * @package    Wpcarparts
 * @subpackage Wpcarparts/includes
 */

class Wpcarparts_Oem_Rest
{

	/**
	 * Register REST routes.
	 */
	public function register_hooks( Wpcarparts_Loader $loader )
	{
		$loader->add_action( 'rest_api_init', $this, 'register_routes' );
	}

	/**
	 * @return void
	 */
	public function register_routes()
	{
		register_rest_route(
			'wpcarparts/v1',
			'/oe-lookup',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_oe_lookup' ),
				'permission_callback' => array( $this, 'can_lookup' ),
				'args'                => array(
					'oePartNumber'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => array( $this, 'sanitize_part_number' ),
					),
					'manufacturer' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Public read; API key never leaves the server.
	 *
	 * @return bool
	 */
	public function can_lookup()
	{
		return true;
	}

	/**
	 * @param string $value
	 * @return string
	 */
	public function sanitize_part_number( $value )
	{
		return preg_replace( '/\s+/', '', sanitize_text_field( $value ) );
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_oe_lookup( WP_REST_Request $request )
	{
		if ( ! Wpcarparts_Config::is_partshub_configured() ) {
			return new WP_Error(
				'wpcarparts_partshub_not_configured',
				__( 'Partshub lookup is not configured.', 'wpcarparts' ),
				array( 'status' => 503 )
			);
		}

		$oe_part_number = $request->get_param( 'oePartNumber' );
		$manufacturer   = $request->get_param( 'manufacturer' );

		if ( $oe_part_number === '' || $manufacturer === '' ) {
			return new WP_Error(
				'wpcarparts_invalid_params',
				__( 'oePartNumber and manufacturer are required.', 'wpcarparts' ),
				array( 'status' => 400 )
			);
		}

		$url = add_query_arg(
			array(
				'oePartNumber' => $oe_part_number,
				'manufacturer' => $manufacturer,
			),
			Wpcarparts_Config::get_partshub_lookup_url()
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'X-API-Key' => Wpcarparts_Config::get_partshub_api_key(),
					'Accept'    => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'wpcarparts_upstream_error',
				$response->get_error_message(),
				array( 'status' => 502 )
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		if ( $status_code === 404 ) {
			return new WP_REST_Response(
				array(
					'found' => false,
					'code'  => is_array( $decoded ) && isset( $decoded['code'] ) ? $decoded['code'] : 'NOT_FOUND',
					'error' => is_array( $decoded ) && isset( $decoded['error'] ) ? $decoded['error'] : '',
				),
				404
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'wpcarparts_upstream_http_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Partshub returned HTTP %d.', 'wpcarparts' ),
					$status_code
				),
				array(
					'status' => 502,
					'body'   => $decoded !== null ? $decoded : $body,
				)
			);
		}

		return rest_ensure_response(
			array(
				'oePartNumber' => $oe_part_number,
				'manufacturer' => $manufacturer,
				'data'         => $decoded !== null ? $decoded : $body,
			)
		);
	}
}
