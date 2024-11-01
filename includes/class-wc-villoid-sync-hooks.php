<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Villoid_Sync_Hooks
{

    protected $villoid_client;
    protected $enabled = true;

    public function __construct(WC_Villoid_Client $villoid_client)
    {
        $this->villoid_client = $villoid_client;
        add_action('wc_villoid_loaded', array($this, 'is_active_on_villoid'));
    }

    public function enable()
    {
        $this->enabled = true;
    }

    public function disable()
    {
        $this->enabled = false;
    }

    public function attach_hooks()
    {
        add_action('edited_product_cat', array($this, 'update_categories'));
        add_action('created_product_cat', array($this, 'update_categories'));
        add_action('added_post_meta', array($this, 'update_products'), 10, 4);
        add_action('updated_post_meta', array($this, 'update_products'), 10, 4);
        add_action('woocommerce_shipping_zone_method_added', array($this, 'update_shipping_zones'), 10, 4);
        add_action('woocommerce_shipping_zone_method_deleted', array($this, 'update_shipping_zones'), 10, 4);
        add_action('woocommerce_shipping_zone_loaded', array($this, 'update_shipping_zones'), 10, 4);
        add_action('woocommerce_delete_shipping_zone', array($this, 'update_shipping_zones'), 10, 4);
    }

    public function is_active_on_villoid()
    {
        $existing_token = get_option('woocommerce_villoid_access_token');
        $user_id = get_option('woocommerce_villoid_user_id');
        $has_auth_keys = get_option('woocommerce_villoid_has_auth_keys');
        if (empty($existing_token) || empty($user_id) || empty($has_auth_keys)) {
            $data = $this->install_plugin();
            $existing_token = $data->token;
            $is_active = $data->is_active;
            if ($this->should_create_auth_keys()) {
                $this->gain_store_api_access();
            }
        } else {
            $is_active = $this->is_active();
        }
        if (!$is_active) {
            $this->disable();
        } else {
            $this->enable();
            $this->attach_hooks();
        }
        if (!empty($existing_token) && $is_active && !$has_auth_keys) {
            $this->villoid_client->set_access_token($existing_token);
        }

        return $is_active;
    }

    private function should_create_auth_keys()
    {
        $gain_inprogress = get_option('woocommerce_villoid_gain_access_in_progress');
        $has_auth_keys = get_option('woocommerce_villoid_has_auth_keys');
        return !$gain_inprogress && !$has_auth_keys;
    }

    private function gain_store_api_access()
    {
        WC_Villoid_Sync_Logger::log('gain_store_api_access: URL: ' . $this->generate_app_auth_url());
        update_option('woocommerce_villoid_gain_access_in_progress', true);
        echo "<script>setTimeout(function(){window.location='" . $this->generate_app_auth_url() . "'}, 300); </script>";
    }

    protected function generate_app_auth_url()
    {
        $user_id = get_option('woocommerce_villoid_user_id');
        $store_url = get_site_url();
        $endpoint = '/wc-auth/v1/authorize';
        $params = [
            'app_name' => 'VILLOID',
            'scope' => 'read_write',
            'user_id' => $user_id,
            'return_url' => get_site_url() . '/wp-admin/plugins.php',
            'callback_url' => $this->villoid_client->get_api_url() . 'auth-callback/'
        ];
        $query_string = http_build_query($params);

        return $store_url . $endpoint . '?' . $query_string;
    }

    public function install_plugin()
    {
        $default_location = wc_get_base_location();
        $data = array(
            'url' => get_site_url(),
            'shipping_zones' => WC_Shipping_Zones::get_zones(),
            'taxmethods' => WC_Tax::get_tax_classes(),
            'default_currency' => get_woocommerce_currency(),
            'country' => $default_location['country'],
        );
        $result = $this->villoid_client->request('Plugin installation or update', 'install', 'POST', $data);
        if (!$result) {
            WC_Admin_Notices::add_notice('Error: VILLOID installation failed. Please contact our team at hello@villoid.com');
            throw new Exception('Error: VILLOID installation failed. Please contact our team at hello@villoid.com');
        } else {
            update_option('woocommerce_villoid_access_token', sanitize_text_field($result->data->token));
            update_option('woocommerce_villoid_user_id', sanitize_text_field($result->data->user_id));
            update_option('woocommerce_villoid_has_auth_keys', sanitize_text_field($result->data->has_auth_keys));
        }
        $this->update_categories();
        return $result->data;
    }

    public function is_active()
    {
        $data = array(
            'token' => get_option('woocommerce_villoid_access_token'),
        );
        $result = $this->villoid_client->request('check if plugin is active', 'is_active', 'POST', $data);
        if (!$result) {
            WC_Admin_Notices::add_notice('Error: VILLOID was unable communicate with server. Please contact our team at hello@villoid.com');
            throw new Exception('Error: VILLOID was unable communicate with server. Please contact our team at hello@villoid.com');
        }
        return $result->data->is_active;
    }

    public function update_categories()
    {
        if ($this->enabled) {
            $term = get_terms('product_cat');
            $data = array(
                'categories' => $term,
                'token' => get_option('woocommerce_villoid_access_token'),
            );
            $this->villoid_client->request('Plugin installation or update', 'categories', 'POST', $data);
        }
    }

    public function udpate_user()
    {
        $default_location = wc_get_base_location();
        $data = array(
            'url' => get_site_url(),
            'shipping_zones' => WC_Shipping_Zones::get_zones(),
            'taxmethods' => WC_Tax::get_tax_classes(),
            'default_currency' => get_woocommerce_currency(),
            'country' => $default_location['country'],
        );
        $result = $this->villoid_client->request('Plugin installation or update', 'update', 'POST', $data);
        if (!$result) {
            WC_Admin_Notices::add_notice('Error: VILLOID Update failed. Please contact our team at hello@villoid.com');
            throw new Exception('Error: VILLOID Update failed. Please contact our team at hello@villoid.com');
        } else {
            update_option('woocommerce_villoid_access_token', sanitize_text_field($result->data->token));
            update_option('woocommerce_villoid_user_id', sanitize_text_field($result->data->user_id));
            update_option('woocommerce_villoid_has_auth_keys', sanitize_text_field($result->data->has_auth_keys));
        }
        return $result->data;
    }

    public function update_products($meta_id, $post_id, $meta_key, $meta_value)
    {
        $product = wc_get_product($post_id);
        if ($product->is_type('grouped')) {
            return;
        }
        if ($product->is_type('variation')) {
            $post_id = $product->get_parent_id();
        }
        if ($this->enabled && $meta_key == '_edit_lock' && get_post_type($post_id) == 'product') {
            $data = array(
                'products' => [$post_id],
                'token' => get_option('woocommerce_villoid_access_token'),
            );
            $this->villoid_client->request('Update product request', 'products', 'POST', $data);
        }
    }

}
