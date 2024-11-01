<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class WC_Square_Client
 *
 * Makes actual HTTP requests to the VILLOID API.
 * Handles:
 * - Authentication
 * - Endpoint selection (API version, Merchant ID in path)
 * - Request retries
 * - Paginated results
 * - Content-Type negotiation (JSON)
 */
class WC_Villoid_Client
{

    protected $access_token;

    public function get_access_token()
    {
        return $this->access_token;
    }

    public function set_access_token($token)
    {
        $this->access_token = $token;
    }

    public function set_merchant_id($merchant_id)
    {
        $this->merchant_id = $merchant_id;
    }

    public function get_api_url()
    {
        return 'https://merchant-api.villoid.com/v1/hooks/woocommerce/';
    }


    public function get_request_args()
    {
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . sanitize_text_field($this->get_access_token()),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'timeout' => 45,
            'httpversion' => '1.1',
        );
        return apply_filters('woocommerce_square_request_args', $args);
    }

    protected function get_request_url($action)
    {
        $api_url_base = trailingslashit($this->get_api_url());
        $request_url = $api_url_base . $action . '/';
        return $request_url;

    }

    public function request($debug_label, $action, $method = 'GET', $body = null)
    {
        // we need to check for cURL
        if (!function_exists('curl_init')) {
            WC_Villoid_Sync_Logger::log('cURL is not available. Sync aborted. Please contact your host to install cURL.');
            return false;
        }

        // The access token is required for all requests
        $access_token = get_option('woocommerce_villoid_access_token');
        if (empty($access_token)) {
            $body['token'] = get_option('woocommerce_villoid_access_token');
        }

        $request_url = $this->get_request_url($action);
        $return_data = array();

        while (true) {
            $response = $this->http_request($debug_label, $request_url, $method, $body);
            if (!$response) {
                return $response;
            }

            $response_data = json_decode(wp_remote_retrieve_body($response));
            // A paged list result will be an array, so let's merge if we're already returning an array
            if (('GET' === $method) && is_array($return_data) && is_array($response_data)) {
                $return_data = array_merge($return_data, $response_data);
            } else {
                $return_data = $response_data;
            }

            $link_header = wp_remote_retrieve_header($response, 'Link');
            // Look for the next page, if specified
            if (!preg_match('/Link:( |)<(.+)>;rel=("|\')next("|\')/i', $link_header)) {
                return $return_data;
            }

            $rel_link_matches = array();
            // Set up the next page URL for the following loop
            if (('GET' === $method) && preg_match('/Link:( |)<(.+)>;rel=("|\')next("|\')/i', $link_header, $rel_link_matches)) {
                $request_url = $rel_link_matches[2];
                $body = null;
            } else {
                return $return_data;
            }
        }
    }

    private function http_request($debug_label, $request_url, $method = 'GET', $body = null)
    {
        $request_args = $this->get_request_args();

        if (!is_null($body)) {
            if (!empty($request_args['headers']['Content-Type']) && ('application/json' === $request_args['headers']['Content-Type'])) {
                $request_args['body'] = json_encode($body);
            } else {
                $request_args['body'] = $body;
            }
        }

        $request_args['method'] = $method;
        // Make actual request in a retry loop
        $try_count = 1;
        $max_retries = 3;

        while (true) {
            $start_time = current_time('timestamp');
            $response = wp_remote_request($request_url, $request_args);
            $end_time = current_time('timestamp');
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                WC_Villoid_Sync_Logger::log('STATUS CODE' . $status_code . '' . $status_code > 300);
            }
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) > 300) {
                WC_Villoid_Sync_Logger::log(sprintf('(%s) Try #%d - %s', $debug_label, $try_count, wp_remote_retrieve_response_code($response)), $start_time, $end_time);
            } else {
                return $response;
            }

            $try_count++;
            if ($try_count > $max_retries) {
                break;
            }
            sleep(1);
        }

        return false;

    }

}
