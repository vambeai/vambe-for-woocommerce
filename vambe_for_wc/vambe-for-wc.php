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
 */

defined('ABSPATH') || exit;

// Plugin constants
define('VAMBE_FOR_WOOCOMMERCE_VERSION', '1.0.0');
define( 'VAMBE_FOR_WOOCOMMERCE_PLUGIN_FILE', __FILE__ );
define('VAMBE_FOR_WOOCOMMERCE_RECOVER_API_ENDPOINT', 'https://webhook.site/36f3e649-4534-4f3b-85fb-4bedc4d55758');

if (!class_exists('Vambe_Loader')) {
    include_once __DIR__ . '/includes/class-vambe-loader.php';
}

// Initialize the loader first
Vambe_Loader::init();

// Now define the constant after initialization
define('VAMBE_TEMP_DIR', Vambe_Loader::get_temp_dir());
define('VAMBE_PLUGIN_URL', VAMBE_TEMP_DIR . '/');


function vambe_get_cart_timeout() {
	$hours = get_option('vambe_cart_timeout', 1);  // Default to 1 hour
	return $hours * HOUR_IN_SECONDS;  // Convert hours to seconds using WordPress constant
}

function vambe_get_api_key() {
	return get_option('vambe_api_key', '');
}

if ( ! class_exists( 'Vambe_For_WooCommerce' ) ) {
	include_once VAMBE_TEMP_DIR. '/class-vambe-for-wc.php';

	// Initialize the plugin
	add_action( 'plugins_loaded', array( 'Vambe_For_WooCommerce', 'init' ) );
}

if ( ! class_exists( 'Vambe_Cart_Tracker' ) ) {
	include_once VAMBE_TEMP_DIR. '/class-vambe-cart-tracker.php';

	// Initialize the plugin
	add_action( 'plugins_loaded', array( 'Vambe_Cart_Tracker', 'init' ) );
}
