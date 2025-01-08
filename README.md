# Vambe for WooCommerce

**Contributors:** Rafael Edwards

**Tags:** woocommerce, cart, products, add-to-cart, url, api, abandoned cart

**Requires at least:** 5.0

**Tested up to:** 6.7.1

**Requires PHP:** 7.4

**Stable tag:** 1.0

Creates a simplified API for WooCommerce products, allows adding multiple products to cart with URL parameters, and includes abandoned cart tracking.

## Description

This plugin provides three main features:

1. A simplified REST API endpoint for WooCommerce products
2. The ability to add multiple products (simple or variable) to the WooCommerce cart using URL parameters
3. Abandoned cart tracking and notification system

### URL Cart Features:

- **URL Format:** `?add-vambe-cart=product_id:quantity,product_id:quantity`
- For variable products: `?add-vambe-cart=product_id_variation_id:quantity`
- **Single Quantity:** If quantity is not specified, the product is added with a quantity of 1

### API Features:

- Endpoint: `/wp-json/wc/v3/vambe-products`
- Provides simplified product data including:
  - Basic product information
  - Pricing
  - Stock status
  - Images
  - Categories and tags
  - Variation data for variable products

### Examples:

- Adding multiple simple products:
  `example.com/cart/?add-vambe-cart=12:2,34:1,56:5`
- Adding variable products:
  `example.com/cart/?add-vambe-cart=12_123:2,34_345:1`

## Installation

1. Upload the plugin files to `/wp-content/plugins/vambe-for-woocommerce`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use the provided URL format to add products to cart or access the API endpoint

## Frequently Asked Questions

### How do I add multiple products to the cart?

Use the URL format: `?add-vambe-cart=product_id:quantity,product_id:quantity`
For variable products, include the variation ID: `product_id_variation_id:quantity`

### How do I access the API?

The API endpoint is available at: `/wp-json/wc/v3/vambe-products`
User must have read permissions to access the endpoint.

## Changelog

### 1.0

- Initial release
- Added multiple products to cart functionality
- Added simplified products API endpoint
- Support for both simple and variable products
- Added abandoned cart tracking system
