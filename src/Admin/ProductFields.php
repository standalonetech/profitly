<?php
/**
 * Supplier product editor fields.
 *
 * @package Profitly
 */

declare( strict_types=1 );

namespace Profitly\Admin;

use Profitly\COGS\ProductCOGS;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves the supplier input in the WooCommerce product editor.
 *
 * Product costs are entered in WooCommerce's own Cost field. This class draws the
 * supplier field for simple products and each variation, only while that Cost field
 * is turned on, and routes the submitted value into the {@see ProductCOGS} data
 * layer. It holds no storage logic of its own. Saved names stay stored while hidden:
 * the save handlers only write a field that was actually posted.
 */
final class ProductFields {

	/**
	 * Register WordPress/WooCommerce hooks for this component.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Simple product: render and save fields.
		add_action( 'woocommerce_product_options_pricing', array( $this, 'render_simple_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_simple_fields' ) );

		// Variations: render fields per variation and save them.
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );
	}

	/**
	 * Render the supplier field for a simple product.
	 *
	 * @return void
	 */
	public function render_simple_fields(): void {
		if ( ! ProductCOGS::is_core_cogs_enabled() ) {
			return;
		}

		global $post;

		$product_id = isset( $post->ID ) ? (int) $post->ID : 0;

		woocommerce_wp_text_input(
			array(
				'id'            => ProductCOGS::META_SUPPLIER,
				'value'         => ProductCOGS::get_supplier( $product_id ),
				'label'         => __( 'Supplier name', 'profitly' ),
				'desc_tip'      => true,
				'description'   => __( 'Optional. Who you buy this product from.', 'profitly' ),
				'wrapper_class' => 'profitly-supplier-field',
			)
		);
	}

	/**
	 * Save the supplier field for a simple product.
	 *
	 * @param WC_Product $product The product being saved.
	 * @return void
	 */
	public function save_simple_fields( WC_Product $product ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product editor nonce before this hook.
		// On variable products the variation rows post this same key as an array
		// (name="_profitly_supplier[loop]"). That belongs to the per-variation save
		// path, so only handle a scalar value here.
		if ( isset( $_POST[ ProductCOGS::META_SUPPLIER ] ) && is_scalar( $_POST[ ProductCOGS::META_SUPPLIER ] ) ) {
			ProductCOGS::save_supplier( $product, wp_unslash( $_POST[ ProductCOGS::META_SUPPLIER ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitised inside ProductCOGS::save_supplier().
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Render the supplier field for a single variation.
	 *
	 * @param int                  $loop           Position of the variation in the editor loop.
	 * @param array<string, mixed> $variation_data Variation data (unused).
	 * @param \WP_Post             $variation      The variation post object.
	 * @return void
	 */
	public function render_variation_fields( int $loop, array $variation_data, \WP_Post $variation ): void {
		unset( $variation_data );

		if ( ! ProductCOGS::is_core_cogs_enabled() ) {
			return;
		}

		$variation_id = (int) $variation->ID;

		woocommerce_wp_text_input(
			array(
				'id'            => ProductCOGS::META_SUPPLIER . '_' . $loop,
				'name'          => ProductCOGS::META_SUPPLIER . '[' . $loop . ']',
				'value'         => get_post_meta( $variation_id, ProductCOGS::META_SUPPLIER, true ),
				'label'         => __( 'Supplier name', 'profitly' ),
				'wrapper_class' => 'form-row form-row-full profitly-supplier-field',
			)
		);
	}

	/**
	 * Save the supplier field for a single variation.
	 *
	 * @param int $variation_id The variation post ID.
	 * @param int $loop         Position of the variation in the editor loop.
	 * @return void
	 */
	public function save_variation_fields( int $variation_id, int $loop ): void {
		$variation = wc_get_product( $variation_id );

		if ( ! $variation instanceof WC_Product ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the variation save nonce before this hook.
		if ( isset( $_POST[ ProductCOGS::META_SUPPLIER ][ $loop ] ) ) {
			ProductCOGS::save_supplier( $variation, wp_unslash( $_POST[ ProductCOGS::META_SUPPLIER ][ $loop ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitised inside ProductCOGS::save_supplier().
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$variation->save();
	}
}
