<?php
/**
 * Plugin Name: Vambe for WooCommerce
 * Description: Creates a simplified API for WooCommerce products, allows adding multiple products to cart with URL parameters, and includes abandoned cart tracking.
 * Version: 1.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Rafael Edwards <rafael.edwards@vambe.ai>
 * Author URI: https://vambe.ai
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: vambe-for-woocommerce
 * Domain Path: /languages
 *
 * @package         Vambe_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'VAMBE_FOR_WOOCOMMERCE_VERSION', '1.0.0' );
define( 'VAMBE_FOR_WOOCOMMERCE_PLUGIN_FILE', __FILE__ );
define( 'VAMBE_FOR_WOOCOMMERCE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

define('VAMBE_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once VAMBE_FOR_WOOCOMMERCE_PLUGIN_PATH . 'includes/class-vambe-settings.php';
Vambe_Settings::init();

function vambe_get_cart_timeout() {
    $hours = get_option('vambe_cart_timeout', 1);  // Default to 1 hour
    return $hours * HOUR_IN_SECONDS;  // Convert hours to seconds using WordPress constant
}

function get_vambe_client_token() {
    return '{{VAMBE_CLIENT_TOKEN}}'; // Esto será reemplazado dinámicamente
}


if ( ! class_exists( 'Vambe_For_WooCommerce' ) ) {
	include_once __DIR__ . '/includes/class-vambe-for-wc.php';

	// Initialize the plugin
	add_action( 'plugins_loaded', array( 'Vambe_For_WooCommerce', 'init' ) );
}

// Ensure Action Scheduler is loaded
add_action('plugins_loaded', function() {
    if (!class_exists('ActionScheduler_DataController')) {
        include_once WP_PLUGIN_DIR . '/woocommerce/packages/action-scheduler/action-scheduler.php';
    }
}, 5); // Priority 5 to load before other plugins

if ( ! class_exists( 'Vambe_Cart_Tracker' ) ) {
    include_once __DIR__ . '/includes/class-vambe-cart-tracker.php';
    
    // Initialize after WooCommerce and Action Scheduler are fully loaded
    add_action( 'woocommerce_after_register_post_type', array( 'Vambe_Cart_Tracker', 'init' ) );
}

// Include script injector
include_once __DIR__ . '/includes/class-vambe-script-injector.php';

// Handle plugin activation
register_activation_hook(__FILE__, 'vambe_plugin_activate');
function vambe_plugin_activate() {
    // Set default values
    add_option('vambe_cart_timeout', 1);
    add_option('vambe_enable_tracking', 'no');
    
    error_log('Vambe plugin activated - attempting to generate API keys');
    
    // Try to generate keys immediately if WooCommerce is already loaded
    if (class_exists('WooCommerce')) {
        error_log('WooCommerce is loaded, generating API keys now');
        vambe_generate_api_keys();
    } else {
        error_log('WooCommerce not loaded yet, scheduling API key generation');
        // Schedule API key generation to run after WooCommerce is fully loaded
        wp_schedule_single_event(time() + 10, 'vambe_generate_api_keys_event');
    }
}

// Register the API key generation event
add_action('vambe_generate_api_keys_event', 'vambe_generate_api_keys');

// Also hook into init to ensure the keys are generated
add_action('init', 'vambe_maybe_generate_api_keys', 999);
function vambe_maybe_generate_api_keys() {
    // Only run this once per session
    static $run_once = false;
    
    if ($run_once) {
        return;
    }
    
    $run_once = true;
    
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        error_log('Vambe: WooCommerce not active during init hook');
        return;
    }
    
    // Check if we already have a Vambe key
    global $wpdb;
    
    if (!isset($wpdb->prefix)) {
        error_log('Vambe: $wpdb not initialized properly');
        return;
    }
    
    $table_name = $wpdb->prefix . 'woocommerce_api_keys';
    
    // Check if the table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
    
    if (!$table_exists) {
        error_log("Vambe: WooCommerce API keys table doesn't exist");
        return;
    }
    
    $existing_key = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT key_id FROM {$wpdb->prefix}woocommerce_api_keys WHERE description = %s",
            'Vambe key'
        )
    );
    
    if ($existing_key) {
        error_log('Vambe: API key already exists, no need to generate');
        return;
    }
    
    error_log('Vambe: No existing API key found, generating now from init hook');
    vambe_generate_api_keys();
}

/**
 * Generate WooCommerce API keys for Vambe if they don't already exist
 */
function vambe_generate_api_keys() {
    error_log('Vambe: Starting API key generation process');
    
    // Make sure WooCommerce is active
    if (!class_exists('WooCommerce')) {
        error_log('Vambe: WooCommerce class not found');
        return;
    }
    
    // Check if we can get the current user
    $user_id = get_current_user_id();
    if (!$user_id) {
        // Try to get an admin user
        $admins = get_users(array('role' => 'administrator', 'number' => 1));
        if (!empty($admins)) {
            $user_id = $admins[0]->ID;
            error_log('Vambe: Using admin user ID: ' . $user_id);
        } else {
            error_log('Vambe: No admin user found');
            return;
        }
    }
    
    error_log('Vambe: Using user ID: ' . $user_id);
    
    global $wpdb;
    
    
    
    // Try to use WooCommerce's built-in function if available
    if (function_exists('wc_create_new_customer_api_key')) {
        error_log('Vambe: Using WooCommerce API key creation function');
        
        try {
            $key_data = wc_create_new_customer_api_key(
                $user_id,
                array(
                    'description' => 'Vambe key',
                    'permissions' => 'read_write',
                )
            );
            
            if ($key_data && isset($key_data['consumer_key']) && isset($key_data['consumer_secret'])) {
                error_log('Vambe: Successfully created API key using WC function');
                
                // Send the API keys to Vambe's service
                vambe_send_api_keys($key_data['consumer_key'], $key_data['consumer_secret']);
                return;
            } else {
                error_log('Vambe: Failed to create API key using WC function');
            }
        } catch (Exception $e) {
            error_log('Vambe: Exception when creating API key: ' . $e->getMessage());
        }
    }
    
    // Fallback to manual creation
    error_log('Vambe: Falling back to manual API key creation');
    
    // Generate API keys
    $consumer_key = 'ck_' . wc_rand_hash();
    $consumer_secret = 'cs_' . wc_rand_hash();
    
    error_log('Vambe: Generated consumer key: ' . $consumer_key);
    
    // Check the table structure to determine available columns
    $table_columns = $wpdb->get_col("DESCRIBE {$wpdb->prefix}woocommerce_api_keys");
    error_log('Vambe: Table columns: ' . print_r($table_columns, true));
    
    // Prepare data based on available columns
    $data = array(
        'user_id'         => $user_id,
        'description'     => 'Vambe key',
        'permissions'     => 'read_write',
        'consumer_key'    => wc_api_hash($consumer_key),
        'consumer_secret' => $consumer_secret,
        'truncated_key'   => substr($consumer_key, -7),
    );
    
    $formats = array(
        '%d',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
    );
    
    
    // Create API key in the database
    $result = $wpdb->insert(
        $wpdb->prefix . 'woocommerce_api_keys',
        $data,
        $formats
    );
    
    if ($result === false) {
        error_log('Vambe: Failed to insert API key into database. DB Error: ' . $wpdb->last_error);
        return;
    }
    
    $key_id = $wpdb->insert_id;
    
    if ($key_id) {
        error_log('Vambe: Successfully created API key with ID: ' . $key_id);
        
        // Send the API keys to Vambe's service
        vambe_send_api_keys($consumer_key, $consumer_secret);
    } else {
        error_log('Vambe: Failed to get insert_id after creating API key');
    }
}

/**
 * Send the generated API keys to Vambe's service
 *
 * @param string $consumer_key The generated consumer key
 * @param string $consumer_secret The generated consumer secret
 */
function vambe_send_api_keys($consumer_key, $consumer_secret) {
    error_log('Vambe: Sending API keys to webhook');
    
    $site_url = get_site_url();
    
    // Prepare the data to send
    $data = array(
        'site_url'        => $site_url,
        'consumer_key'    => $consumer_key,
        'consumer_secret' => $consumer_secret,
    );
    
    // Send the data to Vambe's service
    $response = wp_remote_post('https://5803-186-10-44-110.ngrok-free.app/api/api-token/woocommerce-token', array(
        'body'    => json_encode($data),
        'headers' => array(
            'Content-Type' => 'application/json',
            'x-wc-webhook-source' => $site_url,
        ),
        'timeout' => 30,
    ));
    
    // Log the response for debugging
    if (is_wp_error($response)) {
        error_log('Vambe API key registration error: ' . $response->get_error_message());
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        error_log('Vambe API key registration response: ' . $response_code . ' - ' . $response_body);
    }
}

// Handle plugin deactivation
register_deactivation_hook(__FILE__, 'vambe_plugin_deactivate');
function vambe_plugin_deactivate() {
    // Clear all scheduled actions for this plugin
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('vambe_process_abandoned_carts');
    }
}

// AJAX handler for fetching channels
add_action('wp_ajax_vambe_fetch_channels', 'vambe_ajax_fetch_channels');
function vambe_ajax_fetch_channels() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vambe_fetch_channels')) {
        wp_send_json_error('Invalid security token.');
        return;
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
        return;
    }
    
    $webhook_url = 'https://5803-186-10-44-110.ngrok-free.app/api/webchat/channel';
    
    $args = array(
        'timeout' => 15,
        'headers' => array(
            'x-wc-webhook-source' => get_site_url(),
            'Content-Type' => 'application/json',
            'x-api-key' => get_vambe_client_token(),
        ),
    );
    
    $response = wp_remote_get($webhook_url, $args);
    
    if (is_wp_error($response)) {
        wp_send_json_error('Error connecting to server: ' . $response->get_error_message());
        return;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        wp_send_json_error('Server returned error code: ' . $status_code);
        return;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error('Invalid response format: ' . json_last_error_msg());
        return;
    }
    
    // Filter out deleted channels
    $filtered_data = array_filter($data, function($channel) {
        return !(isset($channel['deleted']) && $channel['deleted'] === true);
    });
    
    // Reset array keys after filtering
    $filtered_data = array_values($filtered_data);
    
    // Store the data in a transient for 1 hour
    set_transient('vambe_channels_data', $data, HOUR_IN_SECONDS);
    
    // Extract client ID from the first non-deleted channel if available
    if (!empty($filtered_data) && isset($filtered_data[0]['client_id'])) {
        update_option('vambe_webchat_client_id', $filtered_data[0]['client_id']);
    }
    
    wp_send_json_success($data);
}
