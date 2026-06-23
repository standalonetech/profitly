<?php
/**
 * Dashboard widget template.
 *
 * Expected scope (provided by {@see \ProfitPress\Dashboard\DashboardWidget::render()}):
 *
 * @var array<string, mixed>                       $current     Current 7-day totals.
 * @var array<string, mixed>|null                  $top_product Top product row, or null.
 * @var string                                     $currency    Store currency code.
 * @var array<string, array<string, mixed>>        $deltas      Delta descriptors per stat.
 *
 * @package ProfitPress
 */

declare( strict_types=1 );

use ProfitPress\Admin\Menu;
use ProfitPress\Reports\ReportsPage;

defined( 'ABSPATH' ) || exit;

$profitpress_previous_label = __( 'vs previous 7 days', 'profitpress' );
?>
<div class="profitpress-widget">
	<?php if ( 0 === (int) $current['order_count'] ) : ?>

		<p><?php esc_html_e( 'No orders in the last 7 days yet.', 'profitpress' ); ?></p>

	<?php else : ?>

		<ul class="profitpress-widget__stats">
			<li>
				<span class="profitpress-widget__label"><?php esc_html_e( 'Net Profit', 'profitpress' ); ?></span>
				<span class="profitpress-widget__value"><?php echo wp_kses_post( wc_price( (float) $current['net_profit'], array( 'currency' => $currency ) ) ); ?></span>
				<span class="profitpress-widget__delta"><?php echo wp_kses_post( ReportsPage::render_delta( $deltas['net_profit'], $profitpress_previous_label ) ); ?></span>
			</li>
			<li>
				<span class="profitpress-widget__label"><?php esc_html_e( 'Revenue', 'profitpress' ); ?></span>
				<span class="profitpress-widget__value"><?php echo wp_kses_post( wc_price( (float) $current['revenue'], array( 'currency' => $currency ) ) ); ?></span>
				<span class="profitpress-widget__delta"><?php echo wp_kses_post( ReportsPage::render_delta( $deltas['revenue'], $profitpress_previous_label ) ); ?></span>
			</li>
			<li>
				<span class="profitpress-widget__label"><?php esc_html_e( 'Margin %', 'profitpress' ); ?></span>
				<span class="profitpress-widget__value"><?php echo esc_html( (string) $current['avg_margin'] ); ?>%</span>
				<span class="profitpress-widget__delta"><?php echo wp_kses_post( ReportsPage::render_delta( $deltas['avg_margin'], $profitpress_previous_label ) ); ?></span>
			</li>
		</ul>

		<?php if ( null !== $top_product ) : ?>
			<p class="profitpress-widget__top">
				<?php
				printf(
					/* translators: 1: product name, 2: profit amount */
					esc_html__( 'Top product: %1$s (%2$s profit)', 'profitpress' ),
					esc_html( (string) $top_product['product_name'] ),
					wp_kses_post( wc_price( (float) $top_product['net_profit'], array( 'currency' => $currency ) ) )
				);
				?>
			</p>
		<?php endif; ?>

	<?php endif; ?>

	<p class="profitpress-widget__link">
		<a href="<?php echo esc_url( Menu::reports_url() ); ?>"><?php esc_html_e( 'View full report →', 'profitpress' ); ?></a>
	</p>
</div>
