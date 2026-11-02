<?php
/**
 * Product-level cost-of-goods source.
 *
 * @package Profitly
 */

declare( strict_types=1 );

namespace Profitly\COGS;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WC_Product;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

/**
 * Read adapter over WooCommerce's built-in Cost of Goods Sold field, plus the
 * supplier name, which Profitly still stores itself.
 *
 * Product costs live in WooCommerce core; Profitly only reads them and freezes
 * them onto orders ({@see OrderLineCOGS}). UI rendering for the supplier field
 * lives in {@see \Profitly\Admin\ProductFields}.
 */
final class ProductCOGS {

	/**
	 * LEGACY meta key: the per-unit cost Profitly stored before 1.0.4.
	 *
	 * Read only by {@see LegacyCostMigration} and kept as the rollback copy. Never
	 * read it for cost anywhere else.
	 */
	public const META_COGS = '_profitly_cogs';

	/**
	 * Meta key holding the optional supplier name.
	 */
	public const META_SUPPLIER = '_profitly_supplier';

	/**
	 * WooCommerce's feature id for Cost of Goods Sold.
	 */
	public const CORE_FEATURE = 'cost_of_goods_sold';

	/**
	 * The product's own cost wins.
	 */
	public const SOURCE_OWN = 'own';

	/**
	 * The parent product's cost wins (a variation inheriting it).
	 */
	public const SOURCE_PARENT = 'parent';

	/**
	 * Both costs count (a variation flagged as additive).
	 */
	public const SOURCE_BOTH = 'both';

	/**
	 * Register WordPress/WooCommerce hooks for this component.
	 *
	 * This data layer is hookless by default. The method exists to satisfy the
	 * uniform component contract called from {@see \Profitly\Plugin}.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Intentionally empty: persistence is invoked by the admin field layer.
	}

	/**
	 * Whether WooCommerce's Cost of Goods Sold feature exists and is turned on.
	 *
	 * Check this before any core cost call: with the feature off those methods
	 * return dummy values and log a "doing it wrong" notice.
	 *
	 * @return bool
	 */
	public static function is_core_cogs_enabled(): bool {
		return class_exists( FeaturesUtil::class )
			&& is_callable( array( FeaturesUtil::class, 'feature_is_enabled' ) )
			&& FeaturesUtil::feature_is_enabled( self::CORE_FEATURE );
	}

	/**
	 * Get the resolved per-unit cost of a product or variation.
	 *
	 * The amount comes from core's own resolver, so it always matches the cost
	 * WooCommerce itself would use, including third-party filters on it.
	 *
	 * @param WC_Product $product The product or variation.
	 * @return string|null The cost as a decimal string; null when no cost is set.
	 */
	public static function get_unit_cost( WC_Product $product ): ?string {
		if ( ! self::is_core_cogs_enabled() ) {
			return null;
		}

		$parent_value = null;
		$additive     = false;

		if ( $product instanceof WC_Product_Variation ) {
			$parent       = wc_get_product( $product->get_parent_id() );
			$parent_value = $parent instanceof WC_Product ? $parent->get_cogs_value() : null;
			$additive     = $product->get_cogs_value_is_additive();
		}

		if ( null === self::resolve_cost_source( $product->get_cogs_value(), $parent_value, $additive ) ) {
			return null;
		}

		return wc_format_decimal( $product->get_cogs_total_value(), false, true );
	}

	/**
	 * Decide which cost values apply. Pure: no WordPress or WooCommerce calls.
	 *
	 * A zero is a real value: variations may genuinely cost nothing, so only null
	 * counts as "not set".
	 *
	 * @param float|null $own         The product's or variation's own cost.
	 * @param float|null $parent_cost The parent product's cost, when there is one.
	 * @param bool       $additive    Whether the variation's cost adds to its parent's.
	 * @return string|null One of the SOURCE_* constants; null when no cost is set.
	 */
	public static function resolve_cost_source( ?float $own, ?float $parent_cost, bool $additive ): ?string {
		if ( null === $own ) {
			return null === $parent_cost ? null : self::SOURCE_PARENT;
		}

		return $additive && null !== $parent_cost ? self::SOURCE_BOTH : self::SOURCE_OWN;
	}

	/**
	 * Get the supplier name for a product or variation.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return string Supplier name, or an empty string when unset.
	 */
	public static function get_supplier( int $product_id ): string {
		$raw = get_post_meta( $product_id, self::META_SUPPLIER, true );

		return is_string( $raw ) ? $raw : '';
	}

	/**
	 * Persist the supplier name against a product or variation.
	 *
	 * @param WC_Product $product   Product or variation object.
	 * @param mixed      $raw_value Raw user-supplied supplier name.
	 * @return string The sanitised supplier name that was stored.
	 */
	public static function save_supplier( WC_Product $product, $raw_value ): string {
		$value = is_scalar( $raw_value ) ? sanitize_text_field( (string) $raw_value ) : '';
		$product->update_meta_data( self::META_SUPPLIER, $value );

		return $value;
	}
}
