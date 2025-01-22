<?php
/**
 * Vambe_For_WooCommerce
 *
 * @package Vambe_For_WooCommerce
 * @since   1.0.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugins main class.
 */
class Vambe_For_WooCommerce {
	const PRODUCT_QTY_MINIMUM    = 1;
	const PRODUCT_TYPE_WHITELIST = array( 'simple', 'variable', 'external' );

	// Prevent instantiation
	private function __construct() {}
	
	// Prevent cloning
	private function __clone() {}
	
	// Prevent unserialize
	private function __wakeup() {}

	/**
	 * Initialize the plugin public actions.
	 */
	public static function init() {
		if ( ! static::is_woocommerce_activated() ) {
			return;
		}

		add_action( 'wp_loaded', array( __CLASS__, 'add_to_cart_action' ), 21 );
		add_action( 'woocommerce_new_order', array( __CLASS__, 'add_order_channel_metadata' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_custom_api_routes' ) );

		load_plugin_textdomain(
			'add-multiple-product-wc-cart',
			false,
			VAMBE_FOR_WOOCOMMERCE_PLUGIN_PATH . 'languages/'
		);
	}

	/**
	 * Returns if WooCommerce is activated
	 */
	private static function is_woocommerce_activated() {
		if ( class_exists( 'woocommerce' ) ) {
			return true;
		} else {
			return false; }
	}

	/**
	 * Process the add to cart url with multiple products
	 *
	 * @return void|false
	 */
	public static function add_to_cart_action() {
		error_log('add_to_cart_action');
		if ( empty( $_REQUEST['add-vambe-cart'] ) ) {
			return;
		}

		if ( is_numeric( $_REQUEST['add-vambe-cart'] ) ) {
			return;
		}

		$product_params = sanitize_text_field( wp_unslash( $_REQUEST['add-vambe-cart'] ) );

		if ( preg_match( '/[^\d\s,:]/', $product_params ) ) {
			return;
		}

		WC()->cart->empty_cart();

		setcookie('vambe_cart', 'true', [
			'expires' => time() + (86400 * 7),
			'path' => COOKIEPATH,
			'domain' => COOKIE_DOMAIN,
			'secure' => is_ssl(),
			'httponly' => true,
			'samesite' => 'Strict'
		]);

		wc_nocache_headers();

		$product_params = trim( $product_params );
		$added_to_cart = array();
		$something_was_added_to_cart = false;

		if ( preg_match_all( '/(\d+)(?::(\d+))?/', $product_params, $products, PREG_SET_ORDER ) ) {
			if ( ! empty( $products ) ) {
				remove_action( 'woocommerce_add_to_cart', array( WC()->cart, 'calculate_totals' ), 20, 0 );

				foreach ( $products as $product_data ) {
					$id = absint($product_data[1]);
					$qty = isset($product_data[2]) ? wc_stock_amount(absint($product_data[2])) : static::PRODUCT_QTY_MINIMUM;
					
					// Try to get the product directly first
					$product = wc_get_product($id);
					
					if (!$product) {
						continue;
					}

					// Determine if this is a variation or a simple product
					if ($product->is_type('variation')) {
						$variation_id = $id;
						$product_id = $product->get_parent_id();
						$variation_data = wc_get_product_variation_attributes($variation_id);
					} else {
						$product_id = $id;
						$variation_id = 0;
						$variation_data = array();
					}

					if (in_array($product->get_type(), static::PRODUCT_TYPE_WHITELIST, true) || $product->is_type('variation')) {
						$passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $qty, $variation_id, $variation_data);

						if ($passed_validation && (false !== WC()->cart->add_to_cart($product_id, $qty, $variation_id, $variation_data))) {
							$something_was_added_to_cart = true;
							$added_to_cart[$id] = $qty;
						}
					}
				}

				add_action( 'woocommerce_add_to_cart', array( WC()->cart, 'calculate_totals' ), 20, 0 );

				if ( $something_was_added_to_cart ) {
					if ( apply_filters( 'add_multiple_to_cart_show_success_message', true, $added_to_cart ) ) {
						wc_add_to_cart_message( $added_to_cart, true );
					}

					WC()->cart->calculate_totals();

					$url = apply_filters( 'add_multiple_to_cart_after_add_redirect_url', null, $added_to_cart );

					if ( $url ) {
							wp_safe_redirect( $url );
							exit;
					} elseif ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
							wp_safe_redirect( wc_get_cart_url() );
							exit;
					}

					// Add checkout ID to cookie if present in the URL (for abandoned cart tracking)
					if (!empty($_REQUEST['checkout-id'])) {
						$checkout_id = sanitize_text_field($_REQUEST['checkout-id']);
						setcookie('vambe_checkout_id', $checkout_id, [
							'expires' => time() + (86400 * 7),
							'path' => COOKIEPATH,
							'domain' => COOKIE_DOMAIN,
							'secure' => is_ssl(),
							'httponly' => true,
							'samesite' => 'Strict'
						]);
					}

					// Add assistant ID to cookie if present in the URL (for direct orders)
					if (!empty($_REQUEST['assistant-id'])) {
						$assistant_id = sanitize_text_field($_REQUEST['assistant-id']);
						setcookie('vambe_assistant_id', $assistant_id, [
							'expires' => time() + (86400 * 7),
							'path' => COOKIEPATH,
							'domain' => COOKIE_DOMAIN,
							'secure' => is_ssl(),
							'httponly' => true,
							'samesite' => 'Strict'
						]);
					}
					// Add contact ID to cookie if present in the URL (for direct orders)
					if (!empty($_REQUEST['contact-id'])) {
						$contact_id = sanitize_text_field($_REQUEST['contact-id']);
						setcookie('vambe_contact_id', $contact_id, [
							'expires' => time() + (86400 * 7),
							'path' => COOKIEPATH,
							'domain' => COOKIE_DOMAIN,
							'secure' => is_ssl(),
							'httponly' => true,
							'samesite' => 'Strict'
						]);
					}
				}
			}
		}
	}

	/**
	 * Add channel metadata to order
	 *
	 * @param WC_Order $order The order object.
	 * @param array    $data  The webhook data.
	 */
	public static function add_order_channel_metadata($order_id, $order) {
		$vambe_order = self::is_vambe_order($order);
	
		error_log("Adding metadata for order: $order_id, is_vambe_order: " . ($vambe_order ? 'true' : 'false'));

		if ($vambe_order) {
			$order->update_meta_data('_wc_order_attribution_utm_source', 'Vambe');
			$order->update_meta_data('channel', 'Vambe');
			
			// Add checkout ID from cookie
			if (isset($_COOKIE['vambe_checkout_id'])) {
				$checkout_id = sanitize_text_field($_COOKIE['vambe_checkout_id']);
				$order->update_meta_data('vambe_checkout_id', $checkout_id);
			}

			// Add assistant ID from cookie
			if (isset($_COOKIE['vambe_assistant_id'])) {
				$assistant_id = sanitize_text_field($_COOKIE['vambe_assistant_id']);
				$order->update_meta_data('vambe_assistant_id', $assistant_id);
			}

			// Add contact ID from cookie
			if (isset($_COOKIE['vambe_contact_id'])) {
				$contact_id = sanitize_text_field($_COOKIE['vambe_contact_id']);
				$order->update_meta_data('vambe_contact_id', $contact_id);
			}
		}

		$order->save();
		error_log('Order metadata saved.');

		// Clear the cookies
		$cookies_to_clear = ['vambe_cart', 'vambe_checkout_id', 'vambe_assistant_id', 'vambe_contact_id'];
		foreach ($cookies_to_clear as $cookie_name) {
			setcookie($cookie_name, '', [
				'expires' => time() - 3600,
				'path' => COOKIEPATH,
				'domain' => COOKIE_DOMAIN,
				'secure' => is_ssl(),
				'httponly' => true,
				'samesite' => 'Strict'
			]);
		}

		error_log('Vambe cookies cleared.');
	}
	

	/**
	 * Check if order contains items added through Vambe cart
	 *
	 * @param WC_Order $order The order object.
	 * @return boolean
	 */
	private static function is_vambe_order($order) {
		// Check for Vambe cookie
		if (isset($_COOKIE['vambe_cart']) && $_COOKIE['vambe_cart'] === 'true') {
			return true;
		}

		// Fallback to checking cart data in order metadata
		$cart_data = $order->get_meta('_cart_data');
		if ($cart_data && strpos($cart_data, 'add-vambe-cart') !== false) {
			return true;
		}

		return false;
	}

	/**
	 * Register custom API routes
	 */
	public static function register_custom_api_routes() {
		register_rest_route('wc/v3', '/vambe-products', array(
			'methods' => 'GET',
			'callback' => array(__CLASS__, 'get_simplified_products'),
			'permission_callback' => function () {
				return current_user_can('read');
			}
		));

		// Add new stock verification endpoint
		register_rest_route('wc/v3', '/vambe-stock', array(
			'methods' => 'GET',
			'callback' => array(__CLASS__, 'get_products_stock'),
			'permission_callback' => function () {
				return current_user_can('read');
			}
		));
	}

	/**
	 * Handle simplified products endpoint
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_simplified_products($request) {
		try {
			$page = $request->get_param('page') ? absint($request->get_param('page')) : 1;
			$per_page = $request->get_param('per_page') ? absint($request->get_param('per_page')) : 10;

			$args = array(
				'status' => 'publish',
				'limit' => $per_page,
				'page' => $page,
				'paginate' => true,
				'orderby' => 'ID',
				'order' => 'DESC',
				// Add post type to ensure we're getting products
				'type' => array_merge(['simple', 'variable', 'external'], array_diff(wc_get_product_types(), ['grouped']))
			);

			if (!function_exists('wc_get_products')) {
				return new WP_Error('woocommerce_required', 'WooCommerce is not active', array('status' => 500));
			}

			$products_query = wc_get_products($args);
			$products = $products_query->products;

			// Log the query results
			error_log("Total products found: " . count($products));
			error_log("Total pages: " . $products_query->max_num_pages);

			$response_data = array();

			foreach ($products as $product) {
				if (!is_object($product)) {
					error_log("Skipping invalid product (not an object)");
					continue;
				}
				
				// Skip grouped products
				if ($product->is_type('grouped')) {
					error_log("Skipping grouped product: " . $product->get_id());
					continue;
				}

				$data = array(
					'id' => $product->get_id(),
					'name' => $product->get_name(),
					'description' => $product->get_description(),
					'short_description' => $product->get_short_description(),
					'price' => $product->get_price(),
					'regular_price' => $product->get_regular_price(),
					'sale_price' => $product->get_sale_price(),
					'on_sale' => $product->is_on_sale(),
					'purchasable' => $product->is_purchasable(),
					'stock_status' => $product->get_stock_status(),
					'image' => self::get_product_image($product),
					'currency' => get_woocommerce_currency(),
					'onlineStoreUrl' => $product->get_permalink(),
					'tags' => wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names')),
					'categories' => wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'))
					// 'metadata' => get_post_meta($product->get_id())
				);

				// Handle variations if it's a variable product
				if ($product->is_type('variable')) {
					try {
						$variations = $product->get_available_variations('objects');
						$variations_res = array();
						$variations_array = array();
						
						if (!empty($variations) && is_array($variations)) {
							foreach ($variations as $variation) {
								$variation_id = $variation->get_id();
								$variation_obj = new WC_Product_Variation($variation_id);
								
								$variations_res = array(
									'id' => $variation_id,
									'name' => $variation_obj->get_name(),
									'on_sale' => $variation_obj->is_on_sale(),
									'regular_price' => (float)$variation_obj->get_regular_price(),
									'sale_price' => (float)$variation_obj->get_sale_price(),
									'description' => $variation_obj->get_description(),
									'image' => self::get_product_image($variation_obj),
									'onlineStoreUrl' => $variation_obj->get_permalink()
									// 'metadata' => get_post_meta($variation_id)
								);
								$attributes = array();
								foreach ($variation_obj->get_variation_attributes() as $attribute_name => $attribute) {
									$attributes[] = array(
										'name' => wc_attribute_label(str_replace('attribute_', '', $attribute_name), $variation_obj),
										'option' => $attribute,
									);
								}

								$variations_res['attributes'] = $attributes;
								$variations_array[] = $variations_res;
							}
						}
						
						$data['product_variations'] = $variations_array;
					} catch (Exception $e) {
						error_log('Error processing variations for product ' . $product->get_id() . ': ' . $e->getMessage());
					}
				}

				$response_data[] = $data;
			}

			return new WP_REST_Response($response_data, 200);

		} catch (Exception $e) {
			return new WP_Error('server_error', $e->getMessage(), array('status' => 500));
		}
	}

	/**
	 * Get product featured image
	 *
	 * @param WC_Product $product Product object
	 * @return string|null
	 */
	private static function get_product_image($product) {
		$image_id = $product->get_image_id();
		if ($image_id) {
			$image_data = wp_get_attachment_image_src($image_id, 'full');
			return $image_data ? $image_data[0] : null;
		}
		return null;
	}

	/**
	 * Handle stock verification endpoint
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_products_stock($request) {
		try {
			$product_ids = $request->get_param('ids');
			
			if (empty($product_ids)) {
				return new WP_Error('missing_ids', 'Product IDs are required', array('status' => 400));
			}

			// Convert comma-separated string to array if necessary
			if (is_string($product_ids)) {
				$product_ids = array_map('absint', explode(',', $product_ids));
			}

			$stock_data = array();

			foreach ($product_ids as $product_id) {
				$product = wc_get_product($product_id);
				
				if (!$product) {
					$stock_data[$product_id] = array(
						'error' => 'Product not found'
					);
					continue;
				}

				if ($product->is_type('variable')) {
					$variations = $product->get_available_variations('objects');
					$variation_stock = array();
					
					foreach ($variations as $variation) {
						$variation_id = $variation->get_id();
						$variation_obj = new WC_Product_Variation($variation_id);
						
						$variation_stock[$variation_id] = array(
							'manage_stock' => $variation_obj->get_manage_stock(),
							'stock_quantity' => $variation_obj->get_stock_quantity(),
							'stock_status' => $variation_obj->get_stock_status(),
							'is_in_stock' => $variation_obj->is_in_stock()
						);
					}

					$stock_data[$product_id] = array(
						'type' => 'variable',
						'variations' => $variation_stock
					);
				} else {
					$stock_data[$product_id] = array(
						'type' => $product->get_type(),
						'manage_stock' => $product->get_manage_stock(),
						'stock_quantity' => $product->get_stock_quantity(),
						'stock_status' => $product->get_stock_status(),
						'is_in_stock' => $product->is_in_stock()
					);
				}
			}

			return new WP_REST_Response($stock_data, 200);

		} catch (Exception $e) {
			return new WP_Error('server_error', $e->getMessage(), array('status' => 500));
		}
	}

}
