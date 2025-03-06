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

    /**
     * Initialize the cart tracker
     * 
     * @return Vambe_Cart_Tracker|null Instance of cart tracker or null if requirements not met
     */
    public static function init() {
        // Check if tracking is enabled
        if (get_option('vambe_enable_tracking', 'no') !== 'yes') {
            error_log('Vambe Cart Tracker: Tracking is disabled');
            return null;
        }

        // Check if WooCommerce is active and fully loaded
        if (!class_exists('WooCommerce') || !function_exists('WC')) {
            error_log('Vambe Cart Tracker: WooCommerce is not active');
            return null;
        }

        // Only create instance if all checks pass
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        // Cart tracking hooks with high priority
        add_action('woocommerce_add_to_cart', array(self::$instance, 'track_cart_changes'), 999);
        add_action('woocommerce_cart_item_removed', array(self::$instance, 'track_cart_changes'), 999);
        add_action('woocommerce_cart_item_restored', array(self::$instance, 'track_cart_changes'), 999);
        add_action('woocommerce_after_cart_item_quantity_update', array(self::$instance, 'track_cart_changes'), 999);
        
        // Debug hook to verify cart functionality
        add_action('woocommerce_cart_loaded_from_session', function() {
            error_log('Vambe Cart Tracker: Cart loaded from session');
            if (WC()->cart) {
                error_log('Vambe Cart Tracker: Cart items: ' . count(WC()->cart->get_cart()));
                
                // Log last activity time if available
                $cart_tracking = WC()->session->get('cart_tracking');
                if ($cart_tracking && isset($cart_tracking['last_activity'])) {
                    error_log('Vambe Cart Tracker: Last cart activity: ' . date('Y-m-d H:i:s', $cart_tracking['last_activity']));
                }
            }
        }, 999);
        
        // Order completion hook
        add_action('woocommerce_new_order', array(self::$instance, 'clear_abandoned_cart'));
        
        // Register action for processing abandoned carts
        add_action('vambe_process_abandoned_carts', array(self::$instance, 'process_abandoned_carts'));
        
        // Verify Action Scheduler availability
        if (!class_exists('ActionScheduler_DataController')) {
            error_log('Vambe Cart Tracker: Action Scheduler class not found');
            return self::$instance;
        }

        if (!function_exists('as_schedule_recurring_action')) {
            error_log('Vambe Cart Tracker: Action Scheduler functions not available');
            return self::$instance;
        }

        try {
            // Check if action is already scheduled
            $next_scheduled = as_next_scheduled_action('vambe_process_abandoned_carts');
            
            if (!$next_scheduled) {
                error_log('Vambe Cart Tracker: No scheduled action found, creating new one');
                
                // Schedule new recurring action
                $scheduled = as_schedule_recurring_action(
                    time(), // When to start
                    15 * MINUTE_IN_SECONDS, // How often to run
                    'vambe_process_abandoned_carts', // Hook to execute
                    array(), // Arguments
                    'vambe-cart-tracker' // Group
                );

                if ($scheduled) {
                    error_log('Vambe Cart Tracker: Successfully scheduled action with ID: ' . $scheduled);
                    error_log('Vambe Cart Tracker: Next run scheduled for: ' . date('Y-m-d H:i:s', time()));
                } else {
                    error_log('Vambe Cart Tracker: Failed to schedule action');
                }
            } else {
                error_log('Vambe Cart Tracker: Action already scheduled for: ' . date('Y-m-d H:i:s', $next_scheduled));
            }

        } catch (Exception $e) {
            error_log('Vambe Cart Tracker: Error scheduling action - ' . $e->getMessage());
        }
        
        error_log('Vambe Cart Tracker: Hooks registered');
        
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
    public function __wakeup() {}

    public function track_cart_changes() {
        error_log('Vambe Cart Tracker: track_cart_changes called');
        try {
            if (!WC()->session) {
                error_log('Vambe Cart Tracker: No session object');
                return;
            }
            
            if (!WC()->cart) {
                error_log('Vambe Cart Tracker: No cart object');
                return;
            }

            error_log('Vambe Cart Tracker: Session and cart available');

            if (WC()->cart->is_empty()) {
                error_log('Vambe Cart Tracker: Cart is empty');
                return;
            }

            error_log('Vambe Cart Tracker: Cart has items');

            $current_time = time();
            $cart_tracking = WC()->session->get('cart_tracking');
            
            if (!$cart_tracking || $this->cart_has_changed($cart_tracking['cart_contents'])) {
                error_log('Vambe Cart Tracker: Cart changes detected');
                $cart_data = array(
                    'cart_contents' => WC()->cart->get_cart_contents(),
                    'last_activity' => $current_time,
                    'notification_sent' => false
                );
                
                WC()->session->set('cart_tracking', $cart_data);
                error_log('Vambe Cart Tracker: Cart data updated - ' . 
                         'Last Activity: ' . date('Y-m-d H:i:s', $current_time) . ' - ' .
                         'Items: ' . count($cart_data['cart_contents']));
                
                // Trigger cart updated webhook
                vambe_trigger_cart_webhook('action.cart_updated');
            }
        } catch (Exception $e) {
            error_log('Vambe Cart Tracker: Error tracking cart changes - ' . $e->getMessage());
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

    public function process_abandoned_carts() {
        error_log('Vambe Cart Tracker: process_abandoned_carts called');
        
        if (!function_exists('WC')) {
            error_log('Vambe Cart Tracker: WooCommerce not loaded');
            return;
        }

        error_log('Vambe Cart Tracker: Starting abandoned cart check');

        // Get last check time
        $last_check = get_option('vambe_last_cart_check', 0);
        $current_time = time();
        
        // Update last check time
        update_option('vambe_last_cart_check', $current_time);
        
        // Get all active sessions
        global $wpdb;
        $sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT session_key, session_value 
                 FROM {$wpdb->prefix}woocommerce_sessions 
                 WHERE session_expiry > UNIX_TIMESTAMP()
                 AND session_value LIKE %s",
                '%cart_tracking%'
            ),
            ARRAY_A
        );
        
        if (empty($sessions)) {
            
            return;
        }
        
        error_log('Vambe Cart Tracker: Processing ' . count($sessions) . ' active sessions');
        
        foreach ($sessions as $session) {
            $this->process_single_session($session);
        }
    }

    private function process_single_session($session) {
        try {
            error_log('Vambe Cart Tracker: Processing session: ' . $session['session_key']);
            $session_data = maybe_unserialize($session['session_value']);

            if (!is_array($session_data) || empty($session_data['cart_tracking'])) {
                error_log('Vambe Cart Tracker: Invalid or empty cart tracking data in session');
                return;
            }
            
            $cart_tracking = is_string($session_data['cart_tracking']) 
                ? maybe_unserialize($session_data['cart_tracking']) 
                : $session_data['cart_tracking'];
                
            if (!is_array($cart_tracking) || !isset($cart_tracking['last_activity'])) {
                error_log('Vambe Cart Tracker: Invalid cart tracking data structure');
                return;
            }
            
            $time_since_last_activity = time() - $cart_tracking['last_activity'];
            error_log('Vambe Cart Tracker: Time since last activity: ' . $time_since_last_activity . ' seconds');
            error_log('Vambe Cart Tracker: Abandon timeout: ' . $this->abandon_timeout . ' seconds');
            error_log('Vambe Cart Tracker: Notification already sent: ' . ($cart_tracking['notification_sent'] ? 'yes' : 'no'));
            
            if ($time_since_last_activity >= $this->abandon_timeout && !$cart_tracking['notification_sent']) {
                error_log('Vambe Cart Tracker: Cart abandoned, processing notification');
                $this->handle_abandoned_cart($session, $session_data, $cart_tracking);
            } else {
                error_log('Vambe Cart Tracker: Cart not abandoned or notification already sent');
            }
        } catch (Exception $e) {
            error_log('Vambe Cart Tracker: Error processing session - ' . $e->getMessage());
        }
    }

    private function handle_abandoned_cart($session, $session_data, $cart_tracking) {
        try {
            $customer = isset($session_data['customer']) ? maybe_unserialize($session_data['customer']) : array();
            
            $customer_data = array(
                'email' => !empty($customer['email']) ? $customer['email'] : 
                         (!empty($customer['id']) ? get_user_by('id', $customer['id'])->user_email : ''),
                'first_name' => !empty($customer['first_name']) ? $customer['first_name'] : '',
                'last_name' => !empty($customer['last_name']) ? $customer['last_name'] : '',
                'phone' => !empty($customer['phone']) ? $customer['phone'] : ''
            );
            
            if (empty($customer_data['phone']) && empty($customer_data['email'])) {
                error_log('Vambe Cart Tracker: No contact information found for customer');
                return;
            }

            error_log('Vambe Cart Tracker: Sending abandoned cart notification');

            if ($this->send_abandoned_cart_notification($customer_data, $cart_tracking)) {
                $this->update_notification_status($session, $session_data, $cart_tracking);
            }
        } catch (Exception $e) {
            error_log('Vambe Cart Tracker: Error handling abandoned cart - ' . $e->getMessage());
        }
    }

    private function send_abandoned_cart_notification($customer_data, $cart_data) {
        try {
            // Trigger abandoned cart webhook with session data
            vambe_trigger_cart_webhook('action.cart_abandoned', array(
                'customer' => $customer_data,
                'cart_tracking' => $cart_data
            ));
            error_log('Vambe Cart Tracker: Sent abandoned cart notification');
            return true;
        } catch (Exception $e) {
            error_log('Vambe Cart Tracker: Error sending notification - ' . $e->getMessage());
            return false;
        }
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

// Register WooCommerce webhook topics and handlers
add_filter('woocommerce_webhook_topics', 'vambe_add_webhook_topics');
add_filter('woocommerce_valid_webhook_resources', 'vambe_add_webhook_resources');
add_filter('woocommerce_valid_webhook_events', 'vambe_add_webhook_events');


function vambe_add_webhook_topics($topics) {
    $topics['action.cart_updated'] = __('Cart Updated (Vambe)', 'vambe-for-woocommerce');
    $topics['action.cart_abandoned'] = __('Cart Abandoned (Vambe)', 'vambe-for-woocommerce');
    return $topics;
}

function vambe_add_webhook_resources($resources) {
    $resources[] = 'action';
    return $resources;
}

function vambe_add_webhook_events($events) {
    $events[] = 'cart_updated';
    $events[] = 'cart_abandoned';
    return $events;
}

// Handle webhook payload delivery
add_filter('woocommerce_webhook_payload', 'vambe_webhook_payload', 10, 4);
function vambe_webhook_payload($payload, $resource, $resource_id, $webhook_id) {
    try {
        // Convert webhook ID to webhook object if needed
        $webhook = is_numeric($webhook_id) ? new WC_Webhook($webhook_id) : $webhook_id;
        $topic = $webhook->get_topic();
        
        if ($topic === 'action.cart_updated' && function_exists('WC') && WC()->session) {
            $customer = WC()->session->get('customer');
            $cart = WC()->cart;
            
            return array(
                'event' => 'cart_updated',
                'cart' => array(
                    'contents' => $cart->get_cart_contents(),
                    'item_count' => count($cart->get_cart_contents()),
                    'total' => $cart->get_cart_contents_total(),
                    'currency' => get_woocommerce_currency()
                ),
                'customer' => array(
                    'email' => !empty($customer['email']) ? $customer['email'] : '',
                    'first_name' => !empty($customer['first_name']) ? $customer['first_name'] : '',
                    'last_name' => !empty($customer['last_name']) ? $customer['last_name'] : '',
                    'phone' => !empty($customer['phone']) ? $customer['phone'] : ''
                ),
                'timestamp' => current_time('mysql')
            );
        }
        
        // For abandoned carts, the payload is handled directly in vambe_trigger_cart_webhook
        // since we need the session data that's not available here during cron jobs
        
        return $payload;
    } catch (Exception $e) {
        error_log('Vambe Cart Tracker: Error generating webhook payload - ' . $e->getMessage());
        return $payload;
    }
}

// Trigger webhooks for cart events
function vambe_trigger_cart_webhook($topic, $session_data = null) {
    try {
        error_log('Vambe Cart Tracker: Attempting to trigger webhook for topic: ' . $topic);
        
        $data_store = WC_Data_Store::load('webhook');
        $webhooks = $data_store->search_webhooks([
            'status' => 'active',
            'limit' => -1,
        ]);
        
        if (empty($webhooks)) {
            error_log('Vambe Cart Tracker: No active webhooks found');
            return;
        }
        
        error_log('Vambe Cart Tracker: Found ' . count($webhooks) . ' active webhooks');

        // Track processed URLs to prevent duplicate deliveries
        $processed_urls = array();
        
        foreach ($webhooks as $webhook_id) {
            $webhook = new WC_Webhook($webhook_id);
            if ($webhook->get_topic() === $topic) {
                // Skip if we've already processed this URL
                $delivery_url = $webhook->get_delivery_url();
                if (in_array($delivery_url, $processed_urls)) {
                    error_log('Vambe Cart Tracker: Deleting duplicate webhook #' . $webhook_id . ' for URL ' . $delivery_url);
                    $webhook->delete(true);
                    continue;
                }
                $processed_urls[] = $delivery_url;

                // Generate payload based on context
                $payload = array();
                if ($topic === 'action.cart_updated' && function_exists('WC') && WC()->session) {
                    $customer = WC()->session->get('customer');
                    $payload = array(
                        'event' => 'cart_updated',
                        'cart' => array(
                            'contents' => WC()->cart->get_cart_contents(),
                            'item_count' => count(WC()->cart->get_cart_contents()),
                            'total' => WC()->cart->get_cart_contents_total(),
                            'currency' => get_woocommerce_currency()
                        ),
                        'customer' => array(
                            'email' => !empty($customer['email']) ? $customer['email'] : '',
                            'first_name' => !empty($customer['first_name']) ? $customer['first_name'] : '',
                            'last_name' => !empty($customer['last_name']) ? $customer['last_name'] : '',
                            'phone' => !empty($customer['phone']) ? $customer['phone'] : ''
                        ),
                        'timestamp' => current_time('mysql')
                    );
                } elseif ($topic === 'action.cart_abandoned' && !empty($session_data)) {
                    $customer = isset($session_data['customer']) ? $session_data['customer'] : array();
                    $cart_tracking = isset($session_data['cart_tracking']) ? $session_data['cart_tracking'] : array();
                    
                    $payload = array(
                        'event' => 'cart_abandoned',
                        'cart' => $cart_tracking,
                        'customer' => array(
                            'email' => !empty($customer['email']) ? $customer['email'] : '',
                            'first_name' => !empty($customer['first_name']) ? $customer['first_name'] : '',
                            'last_name' => !empty($customer['last_name']) ? $customer['last_name'] : '',
                            'phone' => !empty($customer['phone']) ? $customer['phone'] : ''
                        ),
                        'store' => array(
                            'store_name' => get_bloginfo('name')
                        ),
                        'abandoned_at' => current_time('mysql')
                    );
                }

                if (!empty($payload)) {
                    error_log('Vambe Cart Tracker: Delivering webhook to URL: ' . $webhook->get_delivery_url());
                    error_log('Vambe Cart Tracker: Webhook payload: ' . json_encode($payload));
                    $delivery_result = $webhook->deliver($payload);
                    error_log('Vambe Cart Tracker: Webhook delivery result - Response code: ' . 
                             (isset($delivery_result['response']['code']) ? $delivery_result['response']['code'] : 'N/A') . 
                             ', Message: ' . (isset($delivery_result['response']['message']) ? $delivery_result['response']['message'] : 'N/A'));
                } else {
                    error_log('Vambe Cart Tracker: Empty payload for webhook delivery');
                }
            }
        }
    } catch (Exception $e) {
        error_log('Vambe Cart Tracker: Error triggering webhook - ' . $e->getMessage());
    }
}
