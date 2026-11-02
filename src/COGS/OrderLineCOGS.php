<?php
/**
 * Order line-item cost-of-goods snapshotting.
 *
 * @package Profitly
 */

declare( strict_types=1 );

namespace Profitly\COGS;

use WC_Abstract_Order;
use WC_Order;
use WC_Order_Factory;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Snapshots each product's cost of goods onto order line items at sale time.
 *
 * Its single responsibility is preserving historical cost: once an order line
 * item is created its cost is frozen, so later supplier price changes never
 * rewrite past profit. It captures cost across every order-creation path
 * (classic checkout, Blocks checkout, admin, REST, and programmatic creation)
 * and exposes read helpers for downstream reporting.
 *
 * All order access uses the WooCommerce CRUD API, so this is HPOS-safe.
 */
final class OrderLineCOGS {

	/**
	 * Meta key holding the snapshotted per-unit cost.
	 */
	public const META_UNIT = '_profitly_line_cogs';

	/**
	 * Meta key holding the snapshotted line total cost (unit × quantity).
	 */
	public const META_TOTAL = '_profitly_line_cogs_total';

	/**
	 * Meta flag set when no cost was known at sale time. Separates "unknown" from a
	 * real zero cost, so a future backfill tool can find exactly these items.
	 */
	public const META_MISSING = '_profitly_line_cogs_missing';

	/**
	 * Register WordPress/WooCommerce hooks for this component.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Classic + Blocks checkout: fires as each line item is built.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'snapshot_on_checkout' ), 10, 4 );

		// Admin-created and REST-created orders: fires when an item is added.
		add_action( 'woocommerce_new_order_item', array( $this, 'snapshot_on_new_item' ), 10, 3 );

		// Safety net for programmatic orders (e.g. subscription renewals):
		// fill in only the line items still missing a snapshot before save.
		add_action( 'woocommerce_before_order_object_save', array( $this, 'backfill_missing' ), 10, 1 );

		// Core recalculates item costs from the current product cost; keep ours frozen.
		add_filter( 'woocommerce_calculated_order_item_cogs_value', array( $this, 'freeze_core_cost' ), 10, 2 );
	}

	/**
	 * Snapshot cost during checkout (classic and Blocks).
	 *
	 * @param WC_Order_Item_Product $item          The line item being created.
	 * @param string                $cart_item_key The cart item key (unused).
	 * @param array<string, mixed>  $values        Cart item values (unused).
	 * @param WC_Order              $order         The parent order (unused here).
	 * @return void
	 */
	public function snapshot_on_checkout( WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order ): void {
		unset( $cart_item_key, $values, $order );
		$this->apply_snapshot( $item );
	}

	/**
	 * Snapshot cost when an item is added to an admin- or REST-created order.
	 *
	 * @param int           $item_id The order item ID (unused).
	 * @param WC_Order_Item $item    The order item.
	 * @param int           $order_id The parent order ID (unused).
	 * @return void
	 */
	public function snapshot_on_new_item( int $item_id, WC_Order_Item $item, int $order_id ): void {
		unset( $item_id, $order_id );

		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}

		// Only set if missing, so we never clobber a checkout snapshot.
		if ( self::has_snapshot_outcome( $item ) ) {
			return;
		}

		$this->apply_snapshot( $item );
		$item->save();
	}

	/**
	 * Safety net: fill in snapshots missing on any line item before save.
	 *
	 * Covers orders created entirely in code where neither checkout nor the
	 * item hooks ran (for example some subscription renewal flows).
	 *
	 * @param WC_Abstract_Order $order The order about to be saved.
	 * @return void
	 */
	public function backfill_missing( WC_Abstract_Order $order ): void {
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			if ( self::has_snapshot_outcome( $item ) ) {
				continue;
			}

			$this->apply_snapshot( $item );
		}
	}

	/**
	 * Report the frozen cost to WooCommerce when it recalculates an order item.
	 *
	 * Refund items carry no usable total of their own (their negative quantity
	 * clamps to zero), so their cost comes from the refunded line's frozen unit cost.
	 * Without that, core's order cost would stop going down when an order is refunded.
	 *
	 * @param mixed $value The value core calculated; null when it could not.
	 * @param mixed $item  The order item.
	 * @return mixed The frozen cost, or $value untouched when there is no snapshot.
	 */
	public function freeze_core_cost( $value, $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return $value;
		}

		$refunded_item_id = (int) $item->get_meta( '_refunded_item_id', true );

		if ( $refunded_item_id > 0 ) {
			$refunded_item = WC_Order_Factory::get_order_item( $refunded_item_id );
			$unit          = $refunded_item instanceof WC_Order_Item_Product ? (string) $refunded_item->get_meta( self::META_UNIT, true ) : '';

			return '' === $unit ? $value : (float) COGSCalculator::multiply( $unit, (string) $item->get_quantity(), 2 );
		}

		$total = (string) $item->get_meta( self::META_TOTAL, true );

		if ( '' === (string) $item->get_meta( self::META_UNIT, true ) || '' === $total ) {
			return $value;
		}

		return (float) $total;
	}

	/**
	 * Whether no cost was known for this line item when it was sold.
	 *
	 * @param WC_Order_Item_Product $item The order line item.
	 * @return bool
	 */
	public static function is_line_cogs_missing( WC_Order_Item_Product $item ): bool {
		return '' !== (string) $item->get_meta( self::META_MISSING, true );
	}

	/**
	 * Read the snapshotted cost from a line item.
	 *
	 * @param WC_Order_Item_Product $item The order line item.
	 * @return array{unit: string, total: string} The per-unit and total cost.
	 */
	public static function get_line_cogs( WC_Order_Item_Product $item ): array {
		$unit  = (string) $item->get_meta( self::META_UNIT, true );
		$total = (string) $item->get_meta( self::META_TOTAL, true );

		return array(
			'unit'  => '' === $unit ? '0' : $unit,
			'total' => '' === $total ? '0' : $total,
		);
	}

	/**
	 * Sum the snapshotted cost across all line items in an order.
	 *
	 * @param WC_Order $order The order to total.
	 * @return string The total cost of goods as a decimal string.
	 */
	public static function get_order_total_cogs( WC_Order $order ): string {
		$totals = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$totals[] = self::get_line_cogs( $item )['total'];
		}

		return COGSCalculator::sum( $totals );
	}

	/**
	 * Whether a line item already has a snapshot, or was already flagged as missing one.
	 *
	 * @param WC_Order_Item_Product $item The order line item.
	 * @return bool
	 */
	private static function has_snapshot_outcome( WC_Order_Item_Product $item ): bool {
		return '' !== (string) $item->get_meta( self::META_UNIT, true ) || self::is_line_cogs_missing( $item );
	}

	/**
	 * Compute and store the per-unit and total cost on a line item.
	 *
	 * When no cost is known the item is flagged instead: writing '0' would pass an
	 * unknown cost off as a real one, permanently. Does not call save(): checkout and
	 * the order save pipeline persist the item for us; callers that need an immediate
	 * write handle it themselves.
	 *
	 * @param WC_Order_Item_Product $item The line item to annotate.
	 * @return void
	 */
	private function apply_snapshot( WC_Order_Item_Product $item ): void {
		$product   = $item->get_product();
		$unit_cost = $product instanceof WC_Product ? ProductCOGS::get_unit_cost( $product ) : null;

		if ( null === $unit_cost ) {
			$item->update_meta_data( self::META_MISSING, '1' );
			return;
		}

		$quantity   = (int) $item->get_quantity();
		$line_total = COGSCalculator::calculate_line_total( $unit_cost, $quantity );

		$item->update_meta_data( self::META_UNIT, $unit_cost );
		$item->update_meta_data( self::META_TOTAL, $line_total );
	}
}
