<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// if uninstall not called from WordPress exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option('woocommerce_villoid_user_id');
delete_option('woocommerce_villoid_access_token');
delete_option('woocommerce_villoid_has_auth_keys');
delete_option('woocommerce_villoid_gain_access_in_progress');
