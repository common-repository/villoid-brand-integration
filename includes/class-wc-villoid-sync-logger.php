<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Villoid_Sync_Logger
{

    public static $logger;
    const WC_LOG_FILENAME = 'woocommerce-villoid-sync';

    public static function log($message, $start_time = null, $end_time = null)
    {
        if (empty(self::$logger)) {
            self::$logger = new WC_Logger();
        }


        if (!is_null($start_time)) {

            $formatted_start_time = date_i18n(get_option('date_format') . ' g:ia', $start_time);
            $end_time = is_null($end_time) ? current_time('timestamp') : $end_time;
            $formatted_end_time = date_i18n(get_option('date_format') . ' g:ia', $end_time);
            $elapsed_time = round(abs($end_time - $start_time) / 60, 2);

            $log_entry = '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
            $log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";

        } else {

            $log_entry = '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";

        }

        self::$logger->add(self::WC_LOG_FILENAME, $log_entry);
    }
}
