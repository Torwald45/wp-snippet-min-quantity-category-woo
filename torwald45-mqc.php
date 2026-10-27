<?php
/**
 * Plugin Name: Min Quantity by Category for Woo
 * Description: Enforces a minimum purchase quantity for Woo products in selected categories, with optional excluded categories.
 * Version: 1.0
 * Author: Torwald45
 * Author URI: https://github.com/Torwald45
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: torwald45-mqc
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Configuration (optional, add to wp-config.php):
 *
 * define( 'TORWALD45_MQC_INCLUDE', 'flies' ); // product_cat slugs, comma separated
 * define( 'TORWALD45_MQC_EXCLUDE', 'sets' );  // product_cat slugs, comma separated
 * define( 'TORWALD45_MQC_MIN', 6 );           // minimum quantity
 *
 * With no TORWALD45_MQC_INCLUDE defined the snippet does nothing.
 * Per product override: filter torwald45_mqc_min ( $min, $product_id ).
 */

/**
 * Read a comma separated list of category slugs from a constant.
 */
function torwald45_mqc_slugs( $constant ) {
	if ( ! defined( $constant ) ) {
		return array();
	}
	$value = constant( $constant );
	$list  = is_array( $value ) ? $value : explode( ',', (string) $value );
	return array_values( array_filter( array_map( 'sanitize_title', array_map( 'trim', $list ) ) ) );
}

/**
 * Return the parent product ID for a variation, or the ID itself.
 */
function torwald45_mqc_parent_id( $product_id ) {
	$product = wc_get_product( $product_id );
	if ( $product && $product->is_type( 'variation' ) ) {
		return (int) $product->get_parent_id();
	}
	return (int) $product_id;
}

/**
 * Minimum quantity for a product. 0 means no rule applies.
 */
function torwald45_mqc_get_min( $product_id ) {
	$product_id = torwald45_mqc_parent_id( $product_id );
	$include    = torwald45_mqc_slugs( 'TORWALD45_MQC_INCLUDE' );
	$exclude    = torwald45_mqc_slugs( 'TORWALD45_MQC_EXCLUDE' );
	$min        = defined( 'TORWALD45_MQC_MIN' ) ? max( 1, (int) TORWALD45_MQC_MIN ) : 6;

	$applies = ! empty( $include )
		&& has_term( $include, 'product_cat', $product_id )
		&& ! ( ! empty( $exclude ) && has_term( $exclude, 'product_cat', $product_id ) );

	return (int) apply_filters( 'torwald45_mqc_min', $applies ? $min : 0, $product_id );
}

/**
 * Show a single notice about the adjusted quantity.
 */
function torwald45_mqc_notice( $min ) {
	/* translators: %d: minimum quantity */
	$message = sprintf( __( 'Minimum quantity for this product is %d. The quantity has been adjusted.', 'torwald45-mqc' ), $min );
	if ( ! wc_has_notice( $message, 'notice' ) ) {
		wc_add_notice( $message, 'notice' );
	}
}

/**
 * Quantity field on the product page and in the classic cart.
 */
add_filter( 'woocommerce_quantity_input_args', 'torwald45_mqc_input_args', 20, 2 );
function torwald45_mqc_input_args( $args, $product ) {
	if ( ! $product ) {
		return $args;
	}
	$min = torwald45_mqc_get_min( $product->get_id() );
	if ( $min > 1 ) {
		$args['min_value']   = $min;
		$args['input_value'] = max( $min, (int) $args['input_value'] );
	}
	return $args;
}

/**
 * Minimum for variations selected on the product page.
 */
add_filter( 'woocommerce_available_variation', 'torwald45_mqc_variation', 20, 3 );
function torwald45_mqc_variation( $data, $product, $variation ) {
	$min = torwald45_mqc_get_min( $variation->get_id() );
	if ( $min > 1 ) {
		$data['min_qty'] = $min;
	}
	return $data;
}

/**
 * Raise the quantity added to the cart to the minimum.
 */
add_filter( 'woocommerce_add_to_cart_quantity', 'torwald45_mqc_add_quantity', 20, 2 );
function torwald45_mqc_add_quantity( $quantity, $product_id ) {
	$min = torwald45_mqc_get_min( $product_id );
	if ( $min > 1 && $quantity < $min ) {
		torwald45_mqc_notice( $min );
		return $min;
	}
	return $quantity;
}

/**
 * Raise the quantity after a cart update (classic cart and Store API).
 * Quantity 0 removes the item before this action runs, so removal still works.
 */
add_action( 'woocommerce_after_cart_item_quantity_update', 'torwald45_mqc_after_update', 20, 4 );
function torwald45_mqc_after_update( $cart_item_key, $quantity, $old_quantity, $cart ) {
	if ( empty( $cart->cart_contents[ $cart_item_key ] ) ) {
		return;
	}
	$min = torwald45_mqc_get_min( $cart->cart_contents[ $cart_item_key ]['product_id'] );
	if ( $min > 1 && $quantity < $min ) {
		$cart->cart_contents[ $cart_item_key ]['quantity'] = $min;
		torwald45_mqc_notice( $min );
	}
}

/**
 * Fix items already in the cart (e.g. added before the rule existed).
 */
add_action( 'woocommerce_check_cart_items', 'torwald45_mqc_check_cart' );
function torwald45_mqc_check_cart() {
	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		$min = torwald45_mqc_get_min( $cart_item['product_id'] );
		if ( $min > 1 && $cart_item['quantity'] < $min ) {
			WC()->cart->set_quantity( $cart_item_key, $min );
			torwald45_mqc_notice( $min );
		}
	}
}

/**
 * Minimum for the block cart and checkout (Store API).
 */
add_filter( 'woocommerce_store_api_product_quantity_minimum', 'torwald45_mqc_store_api_min', 20, 3 );
function torwald45_mqc_store_api_min( $value, $product, $cart_item ) {
	$min = torwald45_mqc_get_min( $product->get_id() );
	return $min > 1 ? max( (int) $value, $min ) : $value;
}
