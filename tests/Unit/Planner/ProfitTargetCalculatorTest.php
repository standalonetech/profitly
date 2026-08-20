<?php
/**
 * Unit tests for the profit target calculator.
 *
 * @package Profitly
 */

declare( strict_types=1 );

namespace Profitly\Tests\Unit\Planner;

use Profitly\Planner\ProfitTargetCalculator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Profitly\Planner\ProfitTargetCalculator
 */
final class ProfitTargetCalculatorTest extends TestCase {

	/**
	 * Build an aggregation array shaped like ProfitAggregator's output.
	 *
	 * @param string $revenue     Revenue.
	 * @param string $net_profit  Net profit.
	 * @param int    $order_count Orders.
	 * @param string $margin      Net margin in points.
	 * @return array<string, mixed>
	 */
	private function aggregation( string $revenue, string $net_profit, int $order_count, string $margin ): array {
		return array(
			'revenue'     => $revenue,
			'net_profit'  => $net_profit,
			'order_count' => $order_count,
			'avg_margin'  => $margin,
		);
	}

	public function test_baseline_metrics_derive_averages(): void {
		$metrics = ProfitTargetCalculator::baseline_metrics(
			$this->aggregation( '40000.00', '10000.00', 500, '25.00' )
		);

		$this->assertSame( '80.00', $metrics['avg_order_value'] );
		$this->assertSame( '20.00', $metrics['avg_profit_per_order'] );
		$this->assertSame( '25.00', $metrics['margin_percent'] );
		$this->assertSame( 500, $metrics['order_count'] );
		$this->assertFalse( $metrics['low_sample'] );
	}

	public function test_baseline_metrics_with_zero_orders_do_not_divide_by_zero(): void {
		$metrics = ProfitTargetCalculator::baseline_metrics( $this->aggregation( '0.00', '0.00', 0, '0.00' ) );

		$this->assertSame( '0.00', $metrics['avg_order_value'] );
		$this->assertSame( '0.00', $metrics['avg_profit_per_order'] );
		$this->assertFalse( $metrics['low_sample'] );
	}

	public function test_normal_target_calculation(): void {
		// 25% margin, $10,000 target → $40,000 revenue; $80 AOV → 500 orders.
		$result = ProfitTargetCalculator::calculate(
			$this->aggregation( '40000.00', '10000.00', 500, '25.00' ),
			'10000',
			30,
			30
		);

		$this->assertSame( ProfitTargetCalculator::STATUS_OK, $result['status'] );
		$this->assertSame( '40000.00', $result['required_revenue'] );
		$this->assertSame( 500, $result['required_orders'] );
	}

	/**
	 * @dataProvider margin_provider
	 *
	 * @param string $margin   Net margin in points.
	 * @param string $target   Target profit.
	 * @param string $expected Expected required revenue.
	 */
	public function test_required_revenue_across_margins( string $margin, string $target, string $expected ): void {
		$this->assertSame( $expected, ProfitTargetCalculator::revenue_for_margin( $target, $margin ) );
	}

	/**
	 * @return array<string, array{string, string, string}>
	 */
	public function margin_provider(): array {
		return array(
			'20 percent'          => array( '20.00', '10000', '50000.00' ),
			'25 percent'          => array( '25.00', '10000', '40000.00' ),
			'30 percent'          => array( '30.00', '10000', '33333.33' ),
			'40 percent'          => array( '40.00', '10000', '25000.00' ),
			'fractional margin'   => array( '27.40', '10000', '36496.35' ),
			'100 percent margin'  => array( '100.00', '10000', '10000.00' ),
			'zero margin is zero' => array( '0.00', '10000', '0.00' ),
			'negative is zero'    => array( '-5.00', '10000', '0.00' ),
		);
	}

	/**
	 * @dataProvider orders_provider
	 *
	 * @param string $revenue  Required revenue.
	 * @param string $aov      Average order value.
	 * @param int    $expected Expected order count.
	 */
	public function test_required_orders( string $revenue, string $aov, int $expected ): void {
		$this->assertSame( $expected, ProfitTargetCalculator::orders_for_revenue( $revenue, $aov ) );
	}

	/**
	 * @return array<string, array{string, string, int}>
	 */
	public function orders_provider(): array {
		return array(
			'exact division'        => array( '40000.00', '80.00', 500 ),
			'rounds up, never down' => array( '36496.35', '74.82', 488 ),
			'fraction rounds up'    => array( '100.01', '100.00', 2 ),
			'zero aov is zero'      => array( '40000.00', '0.00', 0 ),
			'negative aov is zero'  => array( '40000.00', '-5.00', 0 ),
		);
	}

	public function test_daily_targets_use_inclusive_period_length(): void {
		$result = ProfitTargetCalculator::calculate(
			$this->aggregation( '40000.00', '10000.00', 500, '25.00' ),
			'10000',
			31,
			30
		);

		// 40000 / 31 = 1290.32 ; 500 / 31 = 16.13 → 17 orders a day.
		$this->assertSame( '1290.32', $result['daily_revenue'] );
		$this->assertSame( 17, $result['daily_orders'] );
		$this->assertSame( 31, $result['planning_days'] );
	}

	public function test_zero_day_period_is_clamped_to_one_day(): void {
		$result = ProfitTargetCalculator::calculate(
			$this->aggregation( '40000.00', '10000.00', 500, '25.00' ),
			'10000',
			0,
			30
		);

		$this->assertSame( 1, $result['planning_days'] );
		$this->assertSame( '40000.00', $result['daily_revenue'] );
	}

	public function test_zero_margin_yields_a_dedicated_status(): void {
		$result = ProfitTargetCalculator::calculate(
			$this->aggregation( '40000.00', '0.00', 500, '0.00' ),
			'10000',
			30,
			30
		);

		$this->assertSame( ProfitTargetCalculator::STATUS_NON_POSITIVE_MARGIN, $result['status'] );
		$this->assertSame( '0.00', $result['required_revenue'] );
		$this->assertSame( 0, $result['required_orders'] );
	}

	public function test_negative_margin_yields_a_dedicated_status(): void {
		$result = ProfitTargetCalculator::calculate(
			$this->aggregation( '40000.00', '-2000.00', 500, '-5.00' ),
			'10000',
			30,
			30
		);

		$this->assertSame( ProfitTargetCalculator::STATUS_NON_POSITIVE_MARGIN, $result['status'] );
		$this->assertSame( '0.00', $result['required_revenue'] );
		// The sensitivity table still renders, so the merchant can see what a healthy
		// margin would require.
		$this->assertNotEmpty( $result['sensitivity'] );
	}

	public function test_no_orders_yields_the_empty_status(): void {
		$result = ProfitTargetCalculator::calculate(
			$this->aggregation( '0.00', '0.00', 0, '0.00' ),
			'10000',
			30,
			30
		);

		$this->assertSame( ProfitTargetCalculator::STATUS_NO_DATA, $result['status'] );
		$this->assertSame( 0, $result['required_orders'] );
	}

	/**
	 * @dataProvider invalid_target_provider
	 *
	 * @param string $target Candidate target.
	 */
	public function test_invalid_targets_are_rejected( string $target ): void {
		$this->assertFalse( ProfitTargetCalculator::is_valid_target( $target ) );

		$result = ProfitTargetCalculator::calculate(
			$this->aggregation( '40000.00', '10000.00', 500, '25.00' ),
			$target,
			30,
			30
		);

		$this->assertSame( ProfitTargetCalculator::STATUS_INVALID_TARGET, $result['status'] );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function invalid_target_provider(): array {
		return array(
			'empty'         => array( '' ),
			'zero'          => array( '0' ),
			'negative'      => array( '-1000' ),
			'non numeric'   => array( 'ten thousand' ),
			'above the cap' => array( '1000000001' ),
		);
	}

	public function test_valid_targets_are_accepted(): void {
		$this->assertTrue( ProfitTargetCalculator::is_valid_target( '0.01' ) );
		$this->assertTrue( ProfitTargetCalculator::is_valid_target( '9999.99' ) );
		$this->assertTrue( ProfitTargetCalculator::is_valid_target( ProfitTargetCalculator::MAX_TARGET ) );
	}

	public function test_small_target_keeps_its_decimals(): void {
		$result = ProfitTargetCalculator::calculate(
			$this->aggregation( '1000.00', '250.00', 40, '25.00' ),
			'0.25',
			1,
			30
		);

		$this->assertSame( ProfitTargetCalculator::STATUS_OK, $result['status'] );
		$this->assertSame( '0.25', $result['target_profit'] );
		$this->assertSame( '1.00', $result['required_revenue'] );
		$this->assertSame( 1, $result['required_orders'] );
	}

	public function test_large_target_stays_exact(): void {
		$result = ProfitTargetCalculator::calculate(
			$this->aggregation( '40000.00', '10000.00', 500, '25.00' ),
			'250000000',
			100,
			30
		);

		$this->assertSame( ProfitTargetCalculator::STATUS_OK, $result['status'] );
		$this->assertSame( '1000000000.00', $result['required_revenue'] );
		$this->assertSame( 12500000, $result['required_orders'] );
	}

	public function test_decimal_target_and_margin_round_to_cents(): void {
		$result = ProfitTargetCalculator::calculate(
			$this->aggregation( '36496.35', '10000.00', 488, '27.40' ),
			'10000.49',
			30,
			30
		);

		$this->assertSame( '10000.49', $result['target_profit'] );
		$this->assertSame( '36498.14', $result['required_revenue'] );
		$this->assertSame( '74.79', $result['baseline']['avg_order_value'] );
		$this->assertSame( 489, $result['required_orders'] );
	}

	public function test_low_sample_is_flagged(): void {
		$result = ProfitTargetCalculator::calculate(
			$this->aggregation( '800.00', '200.00', 10, '25.00' ),
			'10000',
			30,
			30
		);

		$this->assertTrue( $result['baseline']['low_sample'] );
		$this->assertSame( ProfitTargetCalculator::STATUS_OK, $result['status'] );
	}

	public function test_projected_profit_scales_the_baseline_to_the_planning_period(): void {
		// $10,000 of profit over 30 baseline days, planned across 60 days → $20,000.
		$result = ProfitTargetCalculator::calculate(
			$this->aggregation( '40000.00', '10000.00', 500, '25.00' ),
			'25000',
			60,
			30
		);

		$this->assertSame( '20000.00', $result['projected_profit'] );
		$this->assertSame( '5000.00', $result['profit_gap'] );
	}

	public function test_profit_gap_is_negative_when_the_pace_already_beats_the_target(): void {
		$result = ProfitTargetCalculator::calculate(
			$this->aggregation( '40000.00', '10000.00', 500, '25.00' ),
			'5000',
			30,
			30
		);

		$this->assertSame( '-5000.00', $result['profit_gap'] );
	}

	public function test_sensitivity_brackets_the_current_margin_and_flags_it(): void {
		$rows = ProfitTargetCalculator::sensitivity( '10000', '27.40' );

		$margins = array_column( $rows, 'margin_percent' );
		$this->assertSame( array( '15.00', '20.00', '25.00', '27.40', '30.00', '35.00' ), $margins );

		$current = array_values( array_filter( $rows, static fn( array $row ): bool => $row['is_current'] ) );
		$this->assertCount( 1, $current );
		$this->assertSame( '27.40', $current[0]['margin_percent'] );
		$this->assertSame( '36496.35', $current[0]['required_revenue'] );

		// The financial point of the table: a higher margin needs less revenue.
		$this->assertLessThan( (float) $rows[0]['required_revenue'], (float) $rows[ count( $rows ) - 1 ]['required_revenue'] );
	}

	public function test_sensitivity_never_lists_a_non_positive_or_above_100_margin(): void {
		foreach ( ProfitTargetCalculator::sensitivity( '10000', '-5.00' ) as $row ) {
			$this->assertGreaterThan( 0.0, (float) $row['margin_percent'] );
			$this->assertFalse( $row['is_current'] );
		}

		foreach ( ProfitTargetCalculator::sensitivity( '10000', '98.00' ) as $row ) {
			$this->assertLessThanOrEqual( 100.0, (float) $row['margin_percent'] );
		}
	}
}
