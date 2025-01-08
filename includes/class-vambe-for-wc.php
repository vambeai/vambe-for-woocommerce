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
	const PRODUCT_TYPE_WHITELIST = array( 'simple', 'variable' );

	private static $cart_tracker = null;

	/**
	 * Initialize the plugin public actions.
	 */
	public static function init() {
		if ( static::is_woocommerce_activated() ) {
			add_action( 'wp_loaded', array( __CLASS__, 'add_to_cart_action' ), 21 );
			add_action( 'woocommerce_new_order', array( __CLASS__, 'add_order_channel_metadata' ), 10, 2 );
			add_action('rest_api_init', array(__CLASS__, 'register_custom_api_routes'));
	

			// Initialize cart tracker
			self::init_cart_tracker();
		}

		load_plugin_textdomain(
			'add-multiple-product-wc-cart',
			false,
			VAMBE_PLUGIN_URL . 'languages/'
		);
	}

	private static function init_cart_tracker() {
		require_once VAMBE_PLUGIN_URL . 'includes/class-vambe-cart-tracker.php';
		self::$cart_tracker = new Vambe_Cart_Tracker();
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

		if ( preg_match( '/[^\d\s,_:]/', $product_params ) ) {
			return;
		}

		WC()->cart->empty_cart();

		setcookie('vambe_cart', 'true', [
			'expires' => time() + (86400 * 3),
			'path' => COOKIEPATH,
			'domain' => COOKIE_DOMAIN,
			'secure' => is_ssl(),
			'httponly' => true,
			'samesite' => 'Strict'
		]);

		wc_nocache_headers();

		$product_params              = trim( $product_params );
		$added_to_cart               = array();
		$something_was_added_to_cart = false;

		if ( preg_match_all( '/(\d+)(?:_(\d+))?(?::(\d+))?/', $product_params, $products, PREG_SET_ORDER ) ) {
			if ( ! empty( $products ) ) {
				remove_action( 'woocommerce_add_to_cart', array( WC()->cart, 'calculate_totals' ), 20, 0 );

				foreach ( $products as $product_data ) {
					$product_id       = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $product_data[1] ) );
					$variation_id     = isset($product_data[2]) ? absint($product_data[2]) : 0;
					$product_qty      = isset($product_data[3]) ? wc_stock_amount( absint( $product_data[3] ) ) : static::PRODUCT_QTY_MINIMUM;
					$product_instance = wc_get_product( $product_id );

					if ( $product_instance && in_array( $product_instance->get_type(), static::PRODUCT_TYPE_WHITELIST, true ) ) {
						$add_to_cart_handler = apply_filters( 'add_multiple_to_cart_cart_handler', $product_instance->get_type(), $product_instance );

						if ( 'simple' === $add_to_cart_handler || 'variable' === $add_to_cart_handler ) {
							$variation_data = array();
							if ('variable' === $add_to_cart_handler && $variation_id) {
								$variation = wc_get_product($variation_id);
								if ($variation && $variation->is_purchasable()) {
									$variation_data = wc_get_product_variation_attributes($variation_id);
								} else {
									continue; // Skip if variation is not valid
								}
							}

							$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $product_qty, $variation_id, $variation_data );

							if ( $passed_validation && ( false !== WC()->cart->add_to_cart( $product_id, $product_qty, $variation_id, $variation_data ) ) ) {
								$something_was_added_to_cart  = true;
								$added_to_cart[ $product_id ] = $product_qty;
							}
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

					// Add checkout ID to cookie if present in the URL
					if (!empty($_REQUEST['checkout-id'])) {
						$checkout_id = sanitize_text_field($_REQUEST['checkout-id']);
						setcookie('vambe_checkout_id', $checkout_id, [
							'expires' => time() + (86400 * 3),
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
		}

		$order->save();
		error_log('Order metadata saved.');

		// Clear the cookies
		$cookies_to_clear = ['vambe_cart', 'vambe_checkout_id'];
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
	}

	/**
	 * Handle simplified products endpoint
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_simplified_products($request) {
		try {
			$args = array(
				'status' => 'publish',
				'limit' => -1,
			);

			if (!function_exists('wc_get_products')) {
				return new WP_Error('woocommerce_required', 'WooCommerce is not active', array('status' => 500));
			}

			$products = wc_get_products($args);
			$response_data = array();

			foreach ($products as $product) {
				if (!is_object($product)) continue;
				
				// Skip grouped products
				if ($product->is_type('grouped')) continue;

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

}
