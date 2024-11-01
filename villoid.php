<?php
/**
 * Plugin Name: VILLOID brand integration
 * Plugin URI: https://villoid.com/
 * Description: An eCommerce toolkit for integration with VILLOID platform
 * Version: 1.0.2
 * Author: VILLOID
 *
 *
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Woocommerce_Villoid')) :
    define('WC_VILLOID_VERSION', '1.0.2');

    class Woocommerce_Villoid
    {

        private static $_instance = null;
        public $villoid_client;
        public $villoid_hooks;
        public $active_on_villoid;

        public static function instance()
        {
            if (is_null(self::$_instance)) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        private function __construct()
        {
            add_action('admin_init', array($this, 'bootstrap'));
        }

        public function bootstrap()
        {
            $this->includes();
            $this->init();
            update_option('woocommerce_villoid_gain_access_in_progress', false);
            do_action('wc_villoid_loaded');
        }

        public function init()
        {
            $this->villoid_client = new WC_Villoid_Client();
            $this->villoid_hooks = new WC_Villoid_Sync_Hooks($this->villoid_client);
        }

        public function includes()
        {
            require_once(dirname(__FILE__) . '/includes/class-wc-villoid-sync-logger.php');
            require_once(dirname(__FILE__) . '/includes/class-wc-villoid-client.php');
            require_once(dirname(__FILE__) . '/includes/class-wc-villoid-sync-hooks.php');
            // enable admin page for villoid
            // require_once(dirname(__FILE__) . '/includes/class-wc-villoid-settings.php');
        }
    }

    Woocommerce_Villoid::instance();

endif;