<?php
/**
 * Top 10 loss-making products partial.
 *
 * Expected scope:
 *
 * @var array<int, array<string, mixed>> $loss_products Ranked product rows (worst first).
 * @var string                           $currency      Store currency code.
 *
 * @package ProfitPress
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>
<div class="profitpress-table-wrap woocommerce-card">
	<h2 class="profitpress-table-title"><?php esc_html_e( 'Top 10 Loss-Making Products', 'profitpress' ); ?></h2>

	<?php if ( empty( $loss_products ) ) : ?>
		<p class="profitpress-table-empty"><?php esc_html_e( 'No loss-making products in this period. ', 'profitpress' ); ?></p>
	<?php else : ?>
		<table class="widefat striped profitpress-product-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'profitpress' ); ?></th>
					<th class="profitpress-num"><?php esc_html_e( 'Units Sold', 'profitpress' ); ?></th>
					<th class="profitpress-num"><?php esc_html_e( 'Revenue', 'profitpress' ); ?></th>
					<th class="profitpress-num"><?php esc_html_e( 'COGS', 'profitpress' ); ?></th>
					<th class="profitpress-num"><?php esc_html_e( 'Net Profit', 'profitpress' ); ?></th>
					<th class="profitpress-num"><?php esc_html_e( 'Margin %', 'profitpress' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $loss_products as $profitpress_row ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( (int) $profitpress_row['product_id'] ) ); ?>">
								<?php echo esc_html( (string) $profitpress_row['product_name'] ); ?>
							</a>
						</td>
						<td class="profitpress-num"><?php echo esc_html( number_format_i18n( (int) $profitpress_row['units_sold'] ) ); ?></td>
						<td class="profitpress-num"><?php echo wp_kses_post( wc_price( (float) $profitpress_row['revenue'], array( 'currency' => $currency ) ) ); ?></td>
						<td class="profitpress-num"><?php echo wp_kses_post( wc_price( (float) $profitpress_row['cogs'], array( 'currency' => $currency ) ) ); ?></td>
						<td class="profitpress-num profitpress-loss"><?php echo wp_kses_post( wc_price( (float) $profitpress_row['net_profit'], array( 'currency' => $currency ) ) ); ?></td>
						<td class="profitpress-num profitpress-loss"><?php echo esc_html( (string) $profitpress_row['margin'] ); ?>%</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
