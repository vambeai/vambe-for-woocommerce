<?php
/**
 * Vambe Cart Tracker
 *
 * @package Vambe_For_WooCommerce
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vambe_Cart_Tracker {
    private $abandon_timeout;
    private static $instance = null;

    public static function init() {
        // Check if WooCommerce is active and fully loaded
        if (!class_exists('WooCommerce') || !function_exists('WC')) {
            error_log('Vambe Cart Tracker: WooCommerce is not active');
            return null;
        }

        if (self::$instance === null) {
            self::$instance = new self();
            
            // Add custom cron interval
            add_filter('cron_schedules', array(self::$instance, 'add_cron_interval'));
            
            // Cart tracking hooks
            add_action('woocommerce_add_to_cart', array(self::$instance, 'track_cart_changes'));
            add_action('woocommerce_cart_item_removed', array(self::$instance, 'track_cart_changes'));
            add_action('woocommerce_cart_item_restored', array(self::$instance, 'track_cart_changes'));
            add_action('woocommerce_after_cart_item_quantity_update', array(self::$instance, 'track_cart_changes'));
            
            // Order completion hook
            add_action('woocommerce_new_order', array(self::$instance, 'clear_abandoned_cart'));
            
            // Abandoned cart check hooks
            add_action('init', array(self::$instance, 'schedule_abandonment_check'));
            add_action('check_abandoned_carts', array(self::$instance, 'process_abandoned_carts'));
            
            error_log('Vambe Cart Tracker: Hooks registered');
        }
        return self::$instance;
    }
    
    private function __construct() {
        error_log('Vambe Cart Tracker: Initializing');
        $this->abandon_timeout = vambe_get_cart_timeout();
        error_log('Vambe Cart Tracker: Abandon timeout set to ' . $this->abandon_timeout . ' seconds');
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialize
    private function __wakeup() {}

    public function add_cron_interval($schedules) {
        // if (!isset($schedules['one_minute'])) {
        //     $schedules['one_minute'] = array(
        //         'interval' => 60,
        //         'display'  => __('Every 1 minute')
        //     );
        //     error_log('Vambe Cart Tracker: Added 1-minute cron interval - Interval value: ' . $schedules['one_minute']['interval']);
        // }
        return $schedules;
    }

    public function track_cart_changes() {
        if (!WC()->session || WC()->cart->is_empty()) {
            error_log('Vambe Cart Tracker: No session or empty cart');
            return;
        }

        $current_time = time();
        $cart_tracking = WC()->session->get('cart_tracking');
        
        if (!$cart_tracking || $this->cart_has_changed($cart_tracking['cart_contents'])) {
            error_log('Vambe Cart Tracker: Cart changes detected');
            $cart_data = array(
                'cart_contents' => WC()->cart->get_cart_contents(),
                'cart_total' => WC()->cart->get_cart_contents_total(),
                'last_activity' => $current_time,
                'notification_sent' => false
            );
            
            WC()->session->set('cart_tracking', $cart_data);
            error_log('Vambe Cart Tracker: Cart data updated - ' . 
                     'Total: ' . $cart_data['cart_total'] . ' - ' . 
                     'Last Activity: ' . date('Y-m-d H:i:s', $current_time) . ' - ' .
                     'Items: ' . count($cart_data['cart_contents']));
        }
    }

    private function cart_has_changed($old_cart) {
        $current_cart = WC()->cart->get_cart_contents();
        return md5(serialize($current_cart)) !== md5(serialize($old_cart));
    }

    public function clear_abandoned_cart($order_id) {
        if (WC()->session) {
            WC()->session->__unset('cart_tracking');
            error_log('Vambe Cart Tracker: Cart tracking cleared for order #' . $order_id);
        }
    }

    public function schedule_abandonment_check() {
        // Clear any existing scheduled events first
        wp_clear_scheduled_hook('check_abandoned_carts');
        
        if (!wp_next_scheduled('check_abandoned_carts')) {
            $scheduled = wp_schedule_event(time(), 'hourly', 'check_abandoned_carts');
            if ($scheduled === false) {
                error_log('Vambe Cart Tracker: Failed to schedule abandonment check');
            } else {
                error_log('Vambe Cart Tracker: Successfully scheduled hourly abandonment check');
            }
        }
        
        // Debug scheduling
        $next_run = wp_next_scheduled('check_abandoned_carts');
        if ($next_run) {
            $time_until_next = $next_run - time();
            error_log('Vambe Cart Tracker: Next check in ' . ($time_until_next / HOUR_IN_SECONDS) . ' hours at: ' . date('Y-m-d H:i:s', $next_run));
        } else {
            error_log('Vambe Cart Tracker: No next run scheduled!');
        }
    }

    public function process_abandoned_carts() {
        if (!function_exists('WC')) {
            error_log('Vambe Cart Tracker: WooCommerce not loaded');
            return;
        }
        
        // Get all active sessions
        global $wpdb;
        $sessions = $wpdb->get_results(
            "SELECT session_key, session_value 
             FROM {$wpdb->prefix}woocommerce_sessions 
             WHERE session_expiry > UNIX_TIMESTAMP()",
            ARRAY_A
        );
        
        if (empty($sessions)) {
            return;
        }
        
        foreach ($sessions as $session) {
            $this->process_single_session($session);
        }
    }

    private function process_single_session($session) {
        $session_data = maybe_unserialize($session['session_value']);
        
        if (!is_array($session_data) || empty($session_data['cart_tracking'])) {
            return;
        }
        
        $cart_tracking = is_string($session_data['cart_tracking']) 
            ? maybe_unserialize($session_data['cart_tracking']) 
            : $session_data['cart_tracking'];
            
        if (!is_array($cart_tracking) || !isset($cart_tracking['last_activity'])) {
            return;
        }
        
        $time_since_last_activity = time() - $cart_tracking['last_activity'];
        
        if ($time_since_last_activity >= $this->abandon_timeout && !$cart_tracking['notification_sent']) {
            $this->handle_abandoned_cart($session, $session_data, $cart_tracking);
        }
    }

    private function handle_abandoned_cart($session, $session_data, $cart_tracking) {
        $customer = isset($session_data['customer']) ? maybe_unserialize($session_data['customer']) : array();
        
        $customer_data = array(
            'email' => !empty($customer['email']) ? $customer['email'] : 
                     (!empty($customer['id']) ? get_user_by('id', $customer['id'])->user_email : ''),
            'first_name' => !empty($customer['first_name']) ? $customer['first_name'] : '',
            'last_name' => !empty($customer['last_name']) ? $customer['last_name'] : '',
            'phone' => !empty($customer['phone']) ? $customer['phone'] : ''
        );
        
        if (empty($customer_data['phone'])) {
            error_log('Vambe Cart Tracker: No phone number found for customer');
            return;
        }

        if ($this->send_abandoned_cart_notification($customer_data, $cart_tracking)) {
            $this->update_notification_status($session, $session_data, $cart_tracking);
        }
    }

    private function send_abandoned_cart_notification($customer_data, $cart_data) {
        $payload = array(
            'customer' => $customer_data,
            'cart' => $cart_data,
            'abandoned_at' => current_time('mysql'),
            'cart_total' => isset($cart_data['cart_total']) ? $cart_data['cart_total'] : 0
        );

        $response = wp_remote_post(VAMBE_FOR_WOOCOMMERCE_RECOVER_API_ENDPOINT, array(
            'body' => json_encode($payload),
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => vambe_get_api_key(),
            ),
        ));

        if (is_wp_error($response)) {
            error_log('Vambe Cart Tracker: Failed to send notification - ' . $response->get_error_message());
            return false;
        }

        return wp_remote_retrieve_response_code($response) === 200;
    }

    private function update_notification_status($session, $session_data, $cart_tracking) {
        global $wpdb;
        
        $cart_tracking['notification_sent'] = true;
        $session_data['cart_tracking'] = $cart_tracking;
        
        $wpdb->update(
            $wpdb->prefix . 'woocommerce_sessions',
            array('session_value' => maybe_serialize($session_data)),
            array('session_key' => $session['session_key'])
        );
    }
} 