<?php
/**
 * Summary KPI cards partial.
 *
 * Expected scope:
 *
 * @var array<int, array<string, mixed>> $cards Card definitions.
 *
 * @package ProfitPress
 */

declare( strict_types=1 );

use ProfitPress\Reports\ReportsPage;

defined( 'ABSPATH' ) || exit;
?>
<div class="profitpress-cards">
	<?php foreach ( $cards as $profitpress_card ) : ?>
		<div class="profitpress-card woocommerce-card components-card">
			<div class="profitpress-card__label"><?php echo esc_html( (string) $profitpress_card['label'] ); ?></div>
			<div class="profitpress-card__value">
				<?php
				// Money values come from wc_price() (trusted HTML); others are plain text.
				echo ! empty( $profitpress_card['is_money'] )
					? wp_kses_post( (string) $profitpress_card['value'] )
					: wp_kses_post( (string) $profitpress_card['value'] );
				?>
			</div>
			<div class="profitpress-card__period"><?php echo esc_html( (string) $profitpress_card['period_label'] ); ?></div>
			<div class="profitpress-card__delta">
				<?php
				echo wp_kses_post(
					ReportsPage::render_delta(
						(array) $profitpress_card['delta'],
						(string) $profitpress_card['previous_label']
					)
				);
				?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
