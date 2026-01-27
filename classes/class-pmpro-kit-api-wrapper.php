<?php

class PMPro_Kit_API_Wrapper {
    private $api_key = '';
    private static $instance = null;

    /**
     * Constructor.
     */
    private function __construct() {
        // Private constructor to prevent direct instantiation.
        $this->api_key = get_option( 'pmprokit_options', array() )['api_key'] ?? '';
    }

    /**
     * Get the singleton instance of the API wrapper.
     *
     * @return PMPro_Kit_API_Wrapper
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get tags.
     *
     * 1.0
     *
     * @return array|WP_Error List of tags or WP_Error on failure.
     */
    public function get_tags() {
        $result = $this->send_request( 'tags', 'GET' );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Sort tags alphabetically by name.
        $tags = empty( $result['tags'] ) ? array() : $result['tags'];
        usort( $tags, function ( $a, $b ) {
            return strcasecmp( $a['name'], $b['name'] );
        } );
        return $tags;
    }

    /**
     * Get subscriber.
     *
     * 1.0
     *
     * @param int $subscriber_id The Kit subscriber ID.
     * @return array|null|WP_Error Subscriber data if available, null if the user does not have a subscriber ID, or WP_Error on failure.
     */
    public function get_subscriber( $subscriber_id ) {
        $response = $this->send_request( 'subscribers/' . intval( $subscriber_id ), 'GET' );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        return $response['subscriber'] ?? null;
    }

    /**
     * Create subscriber.
     *
     * 1.0
     *
     * @param array $data The data for the new subscriber.
     * @return array|WP_Error The created subscriber data or WP_Error on failure.
     */
    public function create_subscriber( $data ) {
        $response = $this->send_request( 'subscribers', 'POST', $data );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        return $response['subscriber'] ?? null;
    }

    /**
     * Update subscriber.
     *
     * 1.0
     *
     * @param int   $subscriber_id The Kit subscriber ID.
     * @param array $data          The data to update for the subscriber.
     * @return array|WP_Error The updated subscriber data or WP_Error on failure.
     */
    public function update_subscriber( $subscriber_id, $data ) {
        $response = $this->send_request( 'subscribers/' . intval( $subscriber_id ), 'PUT', $data );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        return $response['subscriber'] ?? null;
    }

    /**
     * List tags for subscriber.
     *
     * 1.0
     *
     * @param int $subscriber_id The Kit subscriber ID.
     * @return array|WP_Error The list of tags or WP_Error on failure.
     */
    public function list_tags_for_subscriber( $subscriber_id ) {
        $response = $this->send_request( 'subscribers/' . intval( $subscriber_id ) . '/tags', 'GET' );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        return $response['tags'] ?? array();
    }

    /**
     * Tag a subscriber.
     *
     * 1.0
     *
     * @param int $tag_id The Kit tag ID.
     * @param int $subscriber_id The Kit subscriber ID.
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    public function tag_subscriber( $tag_id, $subscriber_id ) {
        return $this->send_request( 'tags/' . intval( $tag_id ) . '/subscribers/' . intval( $subscriber_id ), 'POST' );
    }

    /**
     * Remove a tag from a subscriber.
     *
     * 1.0
     *
     * @param int $tag_id The Kit tag ID.
     * @param int $subscriber_id The Kit subscriber ID.
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    public function remove_tag_from_subscriber( $tag_id, $subscriber_id ) {
        return $this->send_request( 'tags/' . intval( $tag_id ) . '/subscribers/' . intval( $subscriber_id ), 'DELETE' );
    }

    /**
     * Unsubscribe subscriber.
     *
     * 1.0
     *
     * @param int $subscriber_id The Kit subscriber ID.
     * @return array|WP_Error The API response or WP_Error on failure.
     */
    public function unsubscribe_subscriber( $subscriber_id ) {
        return $this->send_request( 'subscribers/' . intval( $subscriber_id ) . '/unsubscribe', 'POST' );
    }

    /**
     * Send API requests to PMPro Kit endpoints.
     *
     * 1.0
     *
     * @param string $endpoint The API endpoint to send the request to.
     * @param string $method   The HTTP method to use for the request (e.g., 'GET', 'POST').
     * @param array  $body     Optional. The body of the request. Default empty array.
     * @return mixed|WP_Error The API response or WP_Error on failure.
     */
    private function send_request( $endpoint, $method, $body = array() ) {
        // Prepare the API request.
        $api_url = 'https://api.kit.com/v4/' . ltrim( $endpoint, '/' );
        $args = array(
            'method'  => $method,
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-Kit-Api-Key' => $this->api_key,
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

        // Check the response code.
        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code < 200 || $response_code >= 300 ) {
            $message = 'API request failed with response code ' . (int) $response_code;
            if ( isset( $response_body['errors'] ) ) {
                $message = 'API Error: ' . esc_html( implode( ', ', $response_body['errors'] ) );
            }
            return new WP_Error(
                'pmprokit_api_error',
                $message,
                array(
                    'status_code' => $response_code,
                    'response_body'  => $response_body,
                )
            );
        }

        // Return the decoded response body.
        return json_decode( wp_remote_retrieve_body( $response ), true );
    }
}
