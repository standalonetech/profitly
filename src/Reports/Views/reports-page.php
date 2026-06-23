<?php
/**
 * Main reports page template.
 *
 * Expected scope (provided by {@see \ProfitPress\Reports\ReportsPage::render()}):
 *
 * @var array<string, mixed>                  $range         Resolved date range.
 * @var string                                $currency      Store currency code.
 * @var array<string, mixed>                  $aggregation   Current-period totals.
 * @var bool                                  $has_data      Whether the range has orders.
 * @var array<int, array<string, mixed>>      $cards         Summary card definitions.
 * @var array<int, array<string, mixed>>      $top_products  Top profitable products.
 * @var array<int, array<string, mixed>>      $loss_products Loss-making products.
 *
 * @package ProfitPress
 */

declare( strict_types=1 );

use ProfitPress\Admin\Menu;
use ProfitPress\Reports\DateRangeFilter;
use ProfitPress\Reports\ReportsPage;

defined( 'ABSPATH' ) || exit;

$profitpress_ranges = array( 'today', '7d', '30d' );
?>
<div class="wrap woocommerce profitpress-reports wc-admin-page">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'ProfitPress Reports', 'profitpress' ); ?></h1>

	<nav class="profitpress-range-selector" aria-label="<?php esc_attr_e( 'Report date range', 'profitpress' ); ?>">
		<?php foreach ( $profitpress_ranges as $profitpress_range_key ) : ?>
			<?php $profitpress_is_active = $range['key'] === $profitpress_range_key; ?>
			<a
				href="<?php echo esc_url( add_query_arg( 'range', $profitpress_range_key, Menu::reports_url() ) ); ?>"
				class="button <?php echo $profitpress_is_active ? 'button-primary' : ''; ?>"
				<?php echo $profitpress_is_active ? 'aria-current="page"' : ''; ?>
			>
				<?php echo esc_html( DateRangeFilter::label_for( $profitpress_range_key ) ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( ! $has_data ) : ?>

		<div class="profitpress-empty-state woocommerce-card">
			<p><?php esc_html_e( 'No orders in this period yet.', 'profitpress' ); ?></p>
		</div>

	<?php else : ?>

		<?php require ReportsPage::view_path( 'summary-cards.php' ); ?>

		<div class="profitpress-tables">
			<?php require ReportsPage::view_path( 'top-products-table.php' ); ?>
			<?php require ReportsPage::view_path( 'bottom-products-table.php' ); ?>
		</div>

		<p class="profitpress-product-footnote description">
			<?php esc_html_e( 'Product net profit excludes gateway fees and shipping costs, which are calculated at the order level.', 'profitpress' ); ?>
		</p>

		<div class="profitpress-export">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="profitpress_export_csv" />
				<?php wp_nonce_field( 'profitpress_export_csv' ); ?>
				<button type="submit" class="button button-secondary">
					<?php esc_html_e( 'Export CSV (last 30 days)', 'profitpress' ); ?>
				</button>
			</form>
		</div>

	<?php endif; ?>
</div>
