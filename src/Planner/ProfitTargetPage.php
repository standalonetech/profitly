<?php
/**
 * Profit Target Planner admin page.
 *
 * @package Profitly
 */

declare( strict_types=1 );

namespace Profitly\Planner;

use DateTimeImmutable;
use Profitly\Constants;
use Profitly\Reports\DateRangeFilter;
use Profitly\Reports\ProfitAggregator;
use Profitly\Reports\ReportCache;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the "Profit Target" page under the top-level Profitly menu.
 *
 * Its single responsibility is orchestration: resolve the requested baseline
 * window, planning period and target profit from the request, pull the (cached)
 * baseline aggregation from {@see ProfitAggregator}, hand it to the WordPress-free
 * {@see ProfitTargetCalculator}, and pass the result to the view. No profit is
 * recalculated here — the baseline is the same aggregate the Reports page shows.
 */
final class ProfitTargetPage {

	/**
	 * Baseline windows offered by the planner, in display order.
	 */
	public const BASELINES = array( '30d', '90d', '12m' );

	/**
	 * The default baseline window.
	 */
	public const DEFAULT_BASELINE = '30d';

	/**
	 * Render the planner page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Constants::CAP_VIEW_REPORTS ) ) {
			wp_die( esc_html__( 'You do not have permission to view Profitly reports.', 'profitly' ) );
		}

		$currency = get_woocommerce_currency();
		$baseline = DateRangeFilter::get_range( self::requested_baseline() );
		$period   = PlanningPeriod::get_current();

		$raw_target      = self::requested_target();
		$target_supplied = '' !== $raw_target;
		$target_invalid  = $target_supplied && ! ProfitTargetCalculator::is_valid_target( $raw_target );

		$aggregation = self::cached_aggregation( $baseline['key'], $baseline['start'], $baseline['end'] );

		$baseline_days = PlanningPeriod::days_between( $baseline['start'], $baseline['end'] );

		$result = ProfitTargetCalculator::calculate(
			$aggregation,
			$target_invalid ? '0' : $raw_target,
			$period['days'],
			$baseline_days
		);

		// Variables consumed by the included template: $currency, $baseline,
		// $period, $raw_target, $target_supplied, $target_invalid, $result.
		require __DIR__ . '/Views/profit-target-page.php';
	}

	/**
	 * Read and validate the requested baseline window key.
	 *
	 * No nonce is needed: this only selects a read-only reporting window.
	 *
	 * @return string One of self::BASELINES.
	 */
	public static function requested_baseline(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only baseline selector, no state change.
		$raw = isset( $_GET['baseline'] ) ? sanitize_key( wp_unslash( $_GET['baseline'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return in_array( $raw, self::BASELINES, true ) ? $raw : self::DEFAULT_BASELINE;
	}

	/**
	 * Read and sanitise the requested target profit.
	 *
	 * The value is run through wc_format_decimal() so locale-formatted input
	 * ("10.000,50") is understood exactly as it is everywhere else in WooCommerce.
	 * Validity (positive, in range) is decided by the calculator, not here.
	 *
	 * @return string Decimal string, or '' when nothing was submitted.
	 */
	public static function requested_target(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only calculation input, no state change.
		$raw = isset( $_GET['target'] ) ? sanitize_text_field( wp_unslash( $_GET['target'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' === trim( $raw ) ) {
			return '';
		}

		return (string) wc_format_decimal( $raw, 2 );
	}

	/**
	 * Fetch (and cache) the baseline aggregation.
	 *
	 * Shares {@see ReportCache} (and therefore its order-event invalidation) with the
	 * Reports page. The key carries the window so a different baseline never reads
	 * another window's numbers; the target profit is deliberately absent because it
	 * changes nothing about the aggregation.
	 *
	 * @param string            $baseline_key The baseline key, for the cache key.
	 * @param DateTimeImmutable $start        Window start.
	 * @param DateTimeImmutable $end          Window end.
	 * @return array<string, mixed> The aggregation.
	 */
	private static function cached_aggregation( string $baseline_key, DateTimeImmutable $start, DateTimeImmutable $end ): array {
		$key    = 'aggregation_baseline_' . $baseline_key . '_' . $start->getTimestamp() . '_' . $end->getTimestamp();
		$cached = ReportCache::get( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$data = ProfitAggregator::aggregate_for_range( $start, $end );
		ReportCache::set( $key, $data );

		return $data;
	}
}
