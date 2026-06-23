<?php
/**
 * Profit breakdown metabox on the order edit screen.
 *
 * @package ProfitPress
 */

declare( strict_types=1 );

namespace ProfitPress\Admin;

use ProfitPress\Profit\OrderProfitCalculator;
use ProfitPress\Shipping\ShippingCostResolver;
use WC_Order;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the per-order profit breakdown and a shipping-cost override field.
 *
 * Its single responsibility is the order-edit UI for profit: a read-only
 * breakdown table from {@see OrderProfitCalculator}, plus one editable override
 * for the merchant-side shipping cost. It registers on both the HPOS order
 * screen and the legacy post-based screen, and reads/writes orders via the
 * WooCommerce CRUD API so it is HPOS-safe.
 */
final class OrderProfitMetaBox {

	/**
	 * Nonce action for saving the override field.
	 */
	private const NONCE = 'profitpress_save_order_profit';

	/**
	 * Register WordPress/WooCommerce hooks for this component.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save' ), 10, 1 );
	}

	/**
	 * Register the metabox on both the HPOS and legacy order screens.
	 *
	 * The HPOS controller class existing does not mean HPOS is the active
	 * storage, so we register on both screen ids; the metabox simply will not
	 * render on whichever screen is not in use.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		$screens = array( 'shop_order' );

		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}

		foreach ( array_unique( array_filter( $screens ) ) as $screen ) {
			add_meta_box(
				'profitpress_order_profit',
				__( 'Profit Breakdown', 'profitpress' ),
				array( $this, 'render' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the metabox contents.
	 *
	 * @param WP_Post|WC_Order $post_or_order The current order, in either form.
	 * @return void
	 */
	public function render( $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order instanceof WP_Post ? $post_or_order->ID : $post_or_order );

		if ( ! $order instanceof WC_Order ) {
			echo '<p>' . esc_html__( 'Profit data is unavailable for this order.', 'profitpress' ) . '</p>';
			return;
		}

		$data     = OrderProfitCalculator::calculate( $order );
		$currency = $data['currency'];

		wp_nonce_field( self::NONCE, 'profitpress_profit_nonce' );

		if ( $data['has_missing_cogs'] ) {
			echo '<p style="color:#b32d2e;">' . esc_html__( 'Some line items have no recorded cost. Profit calculation may be incomplete.', 'profitpress' ) . '</p>';
		}

		$rows = array(
			__( 'Revenue', 'profitpress' )       => $data['revenue'],
			__( 'Cost of goods', 'profitpress' ) => $data['cogs'],
			__( 'Gateway fee', 'profitpress' )   => $data['gateway_fee'],
			__( 'Shipping cost', 'profitpress' ) => $data['shipping_cost'],
		);

		if ( 0.0 !== (float) $data['refund_loss'] ) {
			$rows[ __( 'Lost fees on refunds', 'profitpress' ) ] = $data['refund_loss'];
		}

		echo '<table class="widefat striped" style="margin-bottom:10px;"><tbody>';

		foreach ( $rows as $label => $amount ) {
			printf(
				'<tr><td>%1$s</td><td style="text-align:right;">%2$s</td></tr>',
				esc_html( (string) $label ),
				wp_kses_post( wc_price( (float) $amount, array( 'currency' => $currency ) ) )
			);
		}

		printf(
			'<tr><td><strong>%1$s</strong></td><td style="text-align:right;"><strong>%2$s</strong></td></tr>',
			esc_html__( 'Gross profit', 'profitpress' ),
			wp_kses_post( wc_price( (float) $data['gross_profit'], array( 'currency' => $currency ) ) )
		);

		$net_color = (float) $data['net_profit'] >= 0 ? '#1a7f37' : '#b32d2e';
		printf(
			'<tr><td><strong>%1$s</strong></td><td style="text-align:right;color:%2$s;"><strong>%3$s</strong></td></tr>',
			esc_html__( 'Net profit', 'profitpress' ),
			esc_attr( $net_color ),
			wp_kses_post( wc_price( (float) $data['net_profit'], array( 'currency' => $currency ) ) )
		);

		printf(
			'<tr><td>%1$s</td><td style="text-align:right;">%2$s%%</td></tr>',
			esc_html__( 'Net margin', 'profitpress' ),
			esc_html( $data['margin_percent'] )
		);

		echo '</tbody></table>';

		// Editable override.
		$override = (string) $order->get_meta( ShippingCostResolver::META_OVERRIDE, true );
		printf(
			'<p><label for="profitpress_shipping_override"><strong>%1$s</strong></label><br />%2$s <input type="number" step="0.01" min="0" id="profitpress_shipping_override" name="profitpress_shipping_override" value="%3$s" style="width:120px;" /></p>',
			esc_html__( 'Override shipping cost for this order', 'profitpress' ),
			esc_html( get_woocommerce_currency_symbol( $currency ) ),
			esc_attr( $override )
		);
		echo '<p class="description">' . esc_html__( 'Leave blank to use the zone estimate snapshotted at order creation.', 'profitpress' ) . '</p>';
	}

	/**
	 * Save the shipping cost override.
	 *
	 * @param int $order_id The order id being saved.
	 * @return void
	 */
	public function save( int $order_id ): void {
		if ( ! isset( $_POST['profitpress_profit_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['profitpress_profit_nonce'] ) ), self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$raw = isset( $_POST['profitpress_shipping_override'] ) ? sanitize_text_field( wp_unslash( $_POST['profitpress_shipping_override'] ) ) : '';

		if ( '' === $raw ) {
			$order->delete_meta_data( ShippingCostResolver::META_OVERRIDE );
		} else {
			$clean = wc_format_decimal( $raw, 2, true );
			$order->update_meta_data( ShippingCostResolver::META_OVERRIDE, '' === $clean || (float) $clean < 0 ? '0' : $clean );
		}

		$order->save();
	}
}
