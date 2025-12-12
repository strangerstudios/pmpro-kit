<?php

/**
 * Send API requests to PMPro Kit endpoints.
 *
 * @since TBD
 *
 * @param string $endpoint The API endpoint to send the request to.
 * @param string $method   The HTTP method to use for the request (e.g., 'GET', 'POST').
 * @param array  $body     Optional. The body of the request. Default empty array.
 * @return array|WP_Error The API response or WP_Error on failure.
 */
function pmpro_kit_api_request( $endpoint, $method, $body = array() ) {
	// Prepare the API request.
	$api_url = 'https://api.kit.com/v4/' . ltrim( $endpoint, '/' );
	$args = array(
		'method'  => $method,
		'timeout' => 15,
		'headers' => array(
			'Content-Type' => 'application/json',
			'X-Kit-Api-Key' => '<api-key>'
		),
	);
	if ( ! empty( $body ) ) {
		$args['body'] = wp_json_encode( $body );
	}

	// Make the API request.
	$response = wp_remote_request( $api_url, $args );
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	// Return the decoded response body.
	return json_decode( wp_remote_retrieve_body( $response ), true );
}