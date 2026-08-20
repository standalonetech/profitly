<?php
/**
 * Pure profit-target math.
 *
 * @package Profitly
 */

declare( strict_types=1 );

namespace Profitly\Planner;

use Profitly\COGS\COGSCalculator;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a historical baseline plus a desired profit into the sales required to reach it.
 *
 * Its single responsibility is the target arithmetic. It is stateless and free of
 * WordPress/WooCommerce so it can be unit tested in isolation, and every monetary
 * operation goes through {@see COGSCalculator} (decimal strings, bcmath when
 * available) — never native float arithmetic.
 *
 * It deliberately does **not** recompute revenue, COGS, fees or profit: the baseline
 * it receives is the aggregate already produced by
 * {@see \Profitly\Reports\ProfitAggregator}, so "profit" means exactly what it means
 * everywhere else in Profitly.
 *
 * The core identity is:
 *
 *     required revenue = target profit / net margin
 *     required orders  = required revenue / average order value
 */
final class ProfitTargetCalculator {

	/**
	 * Everything checks out and a target could be calculated.
	 */
	public const STATUS_OK = 'ok';

	/**
	 * The baseline window contains no orders at all.
	 */
	public const STATUS_NO_DATA = 'no_data';

	/**
	 * The baseline margin is zero or negative, so no finite revenue reaches the target.
	 */
	public const STATUS_NON_POSITIVE_MARGIN = 'non_positive_margin';

	/**
	 * The requested target profit is missing, zero or negative.
	 */
	public const STATUS_INVALID_TARGET = 'invalid_target';

	/**
	 * Baseline order count below which the result is flagged as unrepresentative.
	 *
	 * Profitly has no prior convention for a "sample too small" threshold, so this is
	 * a deliberately conservative one: with fewer than 25 orders a single unusual sale
	 * or refund can move the average order value and margin by double digits.
	 */
	public const LOW_SAMPLE_THRESHOLD = 25;

	/**
	 * Largest target profit accepted, in store currency units.
	 *
	 * High enough never to obstruct a real store, low enough to reject typos and
	 * absurd input before it reaches the math.
	 */
	public const MAX_TARGET = '1000000000';

	/**
	 * Step, in margin percentage points, between sensitivity rows.
	 */
	private const SENSITIVITY_STEP = 5;

	/**
	 * How many stepped rows the sensitivity table contains.
	 */
	private const SENSITIVITY_ROWS = 5;

	/**
	 * Derive the per-order averages the planner needs from a raw aggregation.
	 *
	 * Revenue, net profit, order count and margin come straight from the aggregator;
	 * only the two averages are derived here (a division, not a redefinition).
	 *
	 * @param array{revenue: string, net_profit: string, order_count: int, avg_margin: string} $aggregation Baseline aggregation.
	 * @return array{
	 *     revenue: string,
	 *     net_profit: string,
	 *     order_count: int,
	 *     margin_percent: string,
	 *     avg_order_value: string,
	 *     avg_profit_per_order: string,
	 *     low_sample: bool
	 * }
	 */
	public static function baseline_metrics( array $aggregation ): array {
		$revenue     = (string) ( $aggregation['revenue'] ?? '0' );
		$net_profit  = (string) ( $aggregation['net_profit'] ?? '0' );
		$order_count = (int) ( $aggregation['order_count'] ?? 0 );
		$margin      = (string) ( $aggregation['avg_margin'] ?? '0' );
		$orders_str  = (string) $order_count;

		return array(
			'revenue'              => COGSCalculator::add( $revenue, '0', 2 ),
			'net_profit'           => COGSCalculator::add( $net_profit, '0', 2 ),
			'order_count'          => $order_count,
			'margin_percent'       => COGSCalculator::add( $margin, '0', 2 ),
			'avg_order_value'      => COGSCalculator::divide( $revenue, $orders_str, 2 ),
			'avg_profit_per_order' => COGSCalculator::divide( $net_profit, $orders_str, 2 ),
			'low_sample'           => $order_count > 0 && $order_count < self::LOW_SAMPLE_THRESHOLD,
		);
	}

	/**
	 * Work out what has to be sold to hit a target profit.
	 *
	 * @param array<string, mixed> $aggregation    Baseline aggregation from ProfitAggregator.
	 * @param string               $target_profit  Desired profit as a decimal string.
	 * @param int                  $planning_days  Calendar days in the planning period (inclusive).
	 * @param int                  $baseline_days  Calendar days covered by the baseline window.
	 * @return array{
	 *     status: string,
	 *     baseline: array<string, mixed>,
	 *     target_profit: string,
	 *     required_revenue: string,
	 *     required_orders: int,
	 *     daily_revenue: string,
	 *     daily_orders: int,
	 *     planning_days: int,
	 *     projected_profit: string,
	 *     profit_gap: string,
	 *     sensitivity: array<int, array{margin_percent: string, required_revenue: string, is_current: bool}>
	 * }
	 */
	public static function calculate( array $aggregation, string $target_profit, int $planning_days, int $baseline_days ): array {
		$baseline      = self::baseline_metrics( $aggregation );
		$target_profit = self::normalize_target( $target_profit );
		$planning_days = max( 1, $planning_days );
		$baseline_days = max( 1, $baseline_days );

		$result = array(
			'status'           => self::STATUS_OK,
			'baseline'         => $baseline,
			'target_profit'    => $target_profit,
			'required_revenue' => '0.00',
			'required_orders'  => 0,
			'daily_revenue'    => '0.00',
			'daily_orders'     => 0,
			'planning_days'    => $planning_days,
			'projected_profit' => '0.00',
			'profit_gap'       => '0.00',
			'sensitivity'      => array(),
		);

		if ( 0 === $baseline['order_count'] ) {
			$result['status'] = self::STATUS_NO_DATA;

			return $result;
		}

		if ( ! self::is_valid_target( $target_profit ) ) {
			$result['status'] = self::STATUS_INVALID_TARGET;

			return $result;
		}

		// Projecting the baseline's daily profit across the planning period gives an
		// honest "at your current pace" comparison for periods of a different length.
		$result['projected_profit'] = self::projected_profit( (string) $baseline['net_profit'], $baseline_days, $planning_days );
		$result['profit_gap']       = COGSCalculator::subtract( $target_profit, $result['projected_profit'], 2 );
		$result['sensitivity']      = self::sensitivity( $target_profit, $baseline['margin_percent'] );

		if ( (float) $baseline['margin_percent'] <= 0.0 ) {
			// A zero or negative margin means no amount of revenue produces the target;
			// reporting a huge (or negative) number here would be actively misleading.
			$result['status'] = self::STATUS_NON_POSITIVE_MARGIN;

			return $result;
		}

		$required_revenue = self::revenue_for_margin( $target_profit, $baseline['margin_percent'] );
		$required_orders  = self::orders_for_revenue( $required_revenue, $baseline['avg_order_value'] );

		$result['required_revenue'] = $required_revenue;
		$result['required_orders']  = $required_orders;
		$result['daily_revenue']    = COGSCalculator::divide( $required_revenue, (string) $planning_days, 2 );
		$result['daily_orders']     = (int) ceil( $required_orders / $planning_days );

		return $result;
	}

	/**
	 * Revenue needed at a given net margin to produce a given profit.
	 *
	 * @param string $target_profit  Desired profit as a decimal string.
	 * @param string $margin_percent Net margin in percentage points (e.g. '27.40').
	 * @return string Required revenue as a decimal string ('0.00' when the margin is not positive).
	 */
	public static function revenue_for_margin( string $target_profit, string $margin_percent ): string {
		if ( (float) $margin_percent <= 0.0 ) {
			return '0.00';
		}

		// revenue = profit / (margin / 100) — expressed as (profit * 100) / margin so
		// the intermediate stays a decimal string.
		return COGSCalculator::divide(
			COGSCalculator::multiply( $target_profit, '100', 4 ),
			$margin_percent,
			2
		);
	}

	/**
	 * Orders needed to produce a revenue figure at a given average order value.
	 *
	 * Rounded up: a store cannot take a fraction of an order, and rounding down would
	 * describe a plan that lands just short of the target.
	 *
	 * @param string $required_revenue Revenue as a decimal string.
	 * @param string $avg_order_value  Average order value as a decimal string.
	 * @return int Whole orders (0 when the AOV is zero or negative).
	 */
	public static function orders_for_revenue( string $required_revenue, string $avg_order_value ): int {
		if ( (float) $avg_order_value <= 0.0 ) {
			return 0;
		}

		return (int) ceil( (float) COGSCalculator::divide( $required_revenue, $avg_order_value, 4 ) );
	}

	/**
	 * Profit the baseline pace would produce over the planning period.
	 *
	 * @param string $net_profit    Baseline net profit as a decimal string.
	 * @param int    $baseline_days Days covered by the baseline window.
	 * @param int    $planning_days Days in the planning period.
	 * @return string Projected profit as a decimal string.
	 */
	private static function projected_profit( string $net_profit, int $baseline_days, int $planning_days ): string {
		$per_day = COGSCalculator::divide( $net_profit, (string) $baseline_days, 4 );

		return COGSCalculator::multiply( $per_day, (string) $planning_days, 2 );
	}

	/**
	 * Build the margin-sensitivity table.
	 *
	 * The rows are whole multiples of the step (5 points) bracketing the merchant's
	 * own margin, plus a flagged row for that exact margin — so the table always shows
	 * both "where you are" and what a few points either way would do to the revenue
	 * you need. Margins at or below zero are never listed: no finite revenue reaches
	 * the target there.
	 *
	 * @param string $target_profit  Desired profit as a decimal string.
	 * @param string $margin_percent The merchant's current net margin in points.
	 * @return array<int, array{margin_percent: string, required_revenue: string, is_current: bool}>
	 */
	public static function sensitivity( string $target_profit, string $margin_percent ): array {
		$current = (float) $margin_percent;

		// Centre the stepped rows on the current margin (or on the step itself when the
		// margin is not positive, so the table still teaches the relationship).
		$anchor = $current > 0.0 ? (int) ( floor( $current / self::SENSITIVITY_STEP ) * self::SENSITIVITY_STEP ) : self::SENSITIVITY_STEP * 2;
		$first  = $anchor - ( self::SENSITIVITY_STEP * (int) floor( self::SENSITIVITY_ROWS / 2 ) );

		$margins = array();

		for ( $i = 0; $i < self::SENSITIVITY_ROWS; $i++ ) {
			$margins[] = (float) ( $first + ( $i * self::SENSITIVITY_STEP ) );
		}

		if ( $current > 0.0 ) {
			$margins[] = $current;
		}

		$margins = array_filter( $margins, static fn( float $m ): bool => $m > 0.0 && $m <= 100.0 );
		sort( $margins );

		$rows = array();
		$seen = array();

		foreach ( $margins as $margin ) {
			$formatted = COGSCalculator::add( (string) $margin, '0', 2 );

			if ( isset( $seen[ $formatted ] ) ) {
				continue;
			}

			$seen[ $formatted ] = true;

			$rows[] = array(
				'margin_percent'   => $formatted,
				'required_revenue' => self::revenue_for_margin( $target_profit, $formatted ),
				'is_current'       => COGSCalculator::add( $margin_percent, '0', 2 ) === $formatted,
			);
		}

		return $rows;
	}

	/**
	 * Whether a target profit is usable.
	 *
	 * @param string $target_profit Candidate target as a decimal string.
	 * @return bool True when the target is numeric, positive and within range.
	 */
	public static function is_valid_target( string $target_profit ): bool {
		$target_profit = trim( $target_profit );

		if ( '' === $target_profit || ! is_numeric( $target_profit ) ) {
			return false;
		}

		return (float) $target_profit > 0.0 && (float) $target_profit <= (float) self::MAX_TARGET;
	}

	/**
	 * Coerce a raw target into a two-decimal string ('0.00' when unusable).
	 *
	 * @param string $target_profit Raw target.
	 * @return string Decimal string.
	 */
	private static function normalize_target( string $target_profit ): string {
		return COGSCalculator::add( trim( $target_profit ), '0', 2 );
	}
}
