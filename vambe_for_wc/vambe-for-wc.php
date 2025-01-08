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


if (!class_exists('Vambe_Loader')) {
    include_once __DIR__ . '/includes/class-vambe-loader.php';
}

// Initialize the loader
Vambe_Loader::init();