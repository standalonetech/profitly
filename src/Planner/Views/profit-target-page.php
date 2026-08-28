<?php
/**
 * Profit Target Planner page template.
 *
 * Expected scope (provided by {@see \Profitly\Planner\ProfitTargetPage::render()}):
 *
 * @var string               $currency        Store currency code.
 * @var array<string, mixed> $baseline        Resolved baseline window.
 * @var array<string, mixed> $period          Resolved planning period.
 * @var string               $raw_target      The submitted target, sanitised.
 * @var bool                 $target_supplied Whether a target was submitted at all.
 * @var bool                 $target_invalid  Whether the submitted target failed validation.
 * @var array<string, mixed> $result          Calculator output.
 *
 * @package Profitly
 */

declare( strict_types=1 );

use Profitly\Admin\Menu;
use Profitly\Planner\PlanningPeriod;
use Profitly\Planner\ProfitTargetCalculator;
use Profitly\Planner\ProfitTargetPage;
use Profitly\Reports\DateRangeFilter;

defined( 'ABSPATH' ) || exit;

$profitly_metrics = $result['baseline'];
$profitly_money   = static function ( string $amount ) use ( $currency ): string {
	return wc_price( (float) $amount, array( 'currency' => $currency ) );
};
$profitly_dates   = sprintf(
	/* translators: 1: period label, 2: start date, 3: end date, 4: number of days. */
	__( '%1$s — %2$s to %3$s (%4$s days)', 'profitly' ),
	$period['label'],
	date_i18n( (string) get_option( 'date_format' ), $period['start']->getTimestamp() ),
	date_i18n( (string) get_option( 'date_format' ), $period['end']->getTimestamp() ),
	number_format_i18n( (int) $result['planning_days'] )
);
?>
<div class="wrap woocommerce profitly-planner wc-admin-page">
	<div class="profitly-planner__header">
		<h1 class="wp-heading-inline profitly-planner__title"><?php esc_html_e( 'Profit Target Planner', 'profitly' ); ?></h1>
		<p class="profitly-planner__intro"><?php esc_html_e( 'Plan your sales around the profit you want to achieve.', 'profitly' ); ?></p>
	</div>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="profitly-planner__controls woocommerce-card">
		<input type="hidden" name="page" value="<?php echo esc_attr( Menu::TARGET_SLUG ); ?>" />

		<div class="profitly-field">
			<label for="profitly-target">
				<?php
				printf(
					/* translators: %s: store currency code, e.g. USD. */
					esc_html__( 'Target profit (%s)', 'profitly' ),
					esc_html( $currency )
				);
				?>
			</label>
			<input
				type="number"
				step="0.01"
				min="0.01"
				max="<?php echo esc_attr( ProfitTargetCalculator::MAX_TARGET ); ?>"
				inputmode="decimal"
				id="profitly-target"
				name="target"
				value="<?php echo esc_attr( $raw_target ); ?>"
				placeholder="0.00"
				<?php echo $target_invalid ? 'aria-invalid="true"' : ''; ?>
			/>
		</div>

		<div class="profitly-field">
			<label for="profitly-period"><?php esc_html_e( 'Planning period', 'profitly' ); ?></label>
			<select id="profitly-period" name="period">
				<?php foreach ( PlanningPeriod::PERIODS as $profitly_period_key ) : ?>
					<option value="<?php echo esc_attr( $profitly_period_key ); ?>" <?php selected( $period['key'], $profitly_period_key ); ?>>
						<?php echo esc_html( PlanningPeriod::label_for( $profitly_period_key ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="profitly-field">
			<label for="profitly-baseline"><?php esc_html_e( 'Historical baseline', 'profitly' ); ?></label>
			<select id="profitly-baseline" name="baseline">
				<?php foreach ( ProfitTargetPage::BASELINES as $profitly_baseline_key ) : ?>
					<option value="<?php echo esc_attr( $profitly_baseline_key ); ?>" <?php selected( $baseline['key'], $profitly_baseline_key ); ?>>
						<?php echo esc_html( DateRangeFilter::label_for( $profitly_baseline_key ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<fieldset class="profitly-field profitly-field--dates">
			<legend><?php esc_html_e( 'Custom range dates', 'profitly' ); ?></legend>
			<div class="profitly-field__pair">
				<label for="profitly-start" class="screen-reader-text"><?php esc_html_e( 'Custom range start date', 'profitly' ); ?></label>
				<input type="date" id="profitly-start" name="start" value="<?php echo esc_attr( $period['start']->format( 'Y-m-d' ) ); ?>" />
				<span aria-hidden="true" class="profitly-field__separator">&ndash;</span>
				<label for="profitly-end" class="screen-reader-text"><?php esc_html_e( 'Custom range end date', 'profitly' ); ?></label>
				<input type="date" id="profitly-end" name="end" value="<?php echo esc_attr( $period['end']->format( 'Y-m-d' ) ); ?>" />
			</div>
		</fieldset>

		<div class="profitly-field profitly-field--submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Calculate', 'profitly' ); ?></button>
		</div>
	</form>

	<?php if ( $target_invalid ) : ?>
		<div class="notice notice-error inline">
			<p>
				<?php
				printf(
					/* translators: %s: the largest accepted target profit. */
					esc_html__( 'Enter a target profit greater than zero and no larger than %s.', 'profitly' ),
					esc_html( number_format_i18n( (float) ProfitTargetCalculator::MAX_TARGET ) )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ProfitTargetCalculator::STATUS_NO_DATA === $result['status'] ) : ?>

		<div class="profitly-empty-state woocommerce-card">
			<p><?php esc_html_e( 'Profitly needs historical order data to calculate your target.', 'profitly' ); ?></p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: the selected baseline window label, e.g. "Last 30 days". */
					esc_html__( 'There are no orders in the selected baseline (%s). Try a longer baseline once you have made some sales.', 'profitly' ),
					esc_html( $baseline['label'] )
				);
				?>
			</p>
		</div>

	<?php else : ?>

		<?php if ( ProfitTargetCalculator::STATUS_OK === $result['status'] ) : ?>

			<h2 class="profitly-planner__section-title">
				<?php esc_html_e( 'Your profit target', 'profitly' ); ?>
				<span class="profitly-planner__section-note"><?php echo esc_html( $profitly_dates ); ?></span>
			</h2>

			<div class="profitly-cards profitly-cards--four">
				<div class="profitly-card profitly-card--hero woocommerce-card components-card">
					<div class="profitly-card__label"><?php esc_html_e( 'Revenue required', 'profitly' ); ?></div>
					<div class="profitly-card__value"><?php echo wp_kses_post( $profitly_money( (string) $result['required_revenue'] ) ); ?></div>
					<div class="profitly-card__period">
						<?php
						printf(
							/* translators: %s: the target profit. */
							esc_html__( 'to earn %s in profit', 'profitly' ),
							esc_html( wp_strip_all_tags( $profitly_money( (string) $result['target_profit'] ) ) )
						);
						?>
					</div>
				</div>
				<div class="profitly-card woocommerce-card components-card">
					<div class="profitly-card__label"><?php esc_html_e( 'Orders required', 'profitly' ); ?></div>
					<div class="profitly-card__value"><?php echo esc_html( number_format_i18n( (int) $result['required_orders'] ) ); ?></div>
				</div>
				<div class="profitly-card woocommerce-card components-card">
					<div class="profitly-card__label"><?php esc_html_e( 'Revenue per day', 'profitly' ); ?></div>
					<div class="profitly-card__value"><?php echo wp_kses_post( $profitly_money( (string) $result['daily_revenue'] ) ); ?></div>
				</div>
				<div class="profitly-card woocommerce-card components-card">
					<div class="profitly-card__label"><?php esc_html_e( 'Orders per day', 'profitly' ); ?></div>
					<div class="profitly-card__value"><?php echo esc_html( number_format_i18n( (int) $result['daily_orders'] ) ); ?></div>
				</div>
			</div>

			<p class="profitly-planner__explainer">
				<?php
				printf(
					/* translators: 1: required revenue, 2: net margin, 3: target profit. */
					wp_kses_post( __( 'Based on your historical Profitly margin of %2$s, you would need approximately <strong>%1$s</strong> in revenue to generate %3$s in profit.', 'profitly' ) ),
					wp_kses_post( $profitly_money( (string) $result['required_revenue'] ) ),
					esc_html( $profitly_metrics['margin_percent'] . '%' ),
					wp_kses_post( $profitly_money( (string) $result['target_profit'] ) )
				);
				?>
			</p>

		<?php elseif ( ProfitTargetCalculator::STATUS_NON_POSITIVE_MARGIN === $result['status'] ) : ?>

			<div class="notice notice-warning inline">
				<p><strong><?php esc_html_e( 'Your historical margin is not currently sufficient to calculate a revenue target.', 'profitly' ); ?></strong></p>
				<p>
					<?php
					printf(
						/* translators: %s: the merchant's net margin, e.g. "-4.20%". */
						esc_html__( 'Your net margin over this baseline is %s, so selling more at the same margin would not produce the profit you want. Improve profitability first — the table below shows what different margins would require.', 'profitly' ),
						esc_html( $profitly_metrics['margin_percent'] . '%' )
					);
					?>
				</p>
			</div>

		<?php elseif ( ! $target_supplied ) : ?>

			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'Enter a target profit above to see the revenue and orders you would need.', 'profitly' ); ?></p>
			</div>

		<?php endif; ?>

		<h2 class="profitly-planner__section-title">
			<?php esc_html_e( 'Current performance', 'profitly' ); ?>
			<span class="profitly-planner__section-note"><?php echo esc_html( $baseline['label'] ); ?></span>
		</h2>

		<div class="profitly-cards profitly-cards--five">
			<div class="profitly-card woocommerce-card components-card">
				<div class="profitly-card__label"><?php esc_html_e( 'Revenue', 'profitly' ); ?></div>
				<div class="profitly-card__value"><?php echo wp_kses_post( $profitly_money( (string) $profitly_metrics['revenue'] ) ); ?></div>
			</div>
			<div class="profitly-card woocommerce-card components-card">
				<div class="profitly-card__label"><?php esc_html_e( 'Orders', 'profitly' ); ?></div>
				<div class="profitly-card__value"><?php echo esc_html( number_format_i18n( (int) $profitly_metrics['order_count'] ) ); ?></div>
			</div>
			<div class="profitly-card woocommerce-card components-card">
				<div class="profitly-card__label"><?php esc_html_e( 'Average order value', 'profitly' ); ?></div>
				<div class="profitly-card__value"><?php echo wp_kses_post( $profitly_money( (string) $profitly_metrics['avg_order_value'] ) ); ?></div>
			</div>
			<div class="profitly-card woocommerce-card components-card">
				<div class="profitly-card__label"><?php esc_html_e( 'Net profit', 'profitly' ); ?></div>
				<div class="profitly-card__value"><?php echo wp_kses_post( $profitly_money( (string) $profitly_metrics['net_profit'] ) ); ?></div>
			</div>
			<div class="profitly-card woocommerce-card components-card">
				<div class="profitly-card__label"><?php esc_html_e( 'Net margin', 'profitly' ); ?></div>
				<div class="profitly-card__value"><?php echo esc_html( $profitly_metrics['margin_percent'] . '%' ); ?></div>
			</div>
		</div>

		<?php if ( ! empty( $profitly_metrics['low_sample'] ) ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<?php
					printf(
						/* translators: %s: number of orders in the baseline. */
						esc_html__( 'This calculation is based on a limited number of orders (%s) and may not be representative. A longer baseline gives a steadier average.', 'profitly' ),
						esc_html( number_format_i18n( (int) $profitly_metrics['order_count'] ) )
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<div class="profitly-tables">

			<?php if ( ProfitTargetCalculator::STATUS_OK === $result['status'] ) : ?>
				<div class="profitly-table-wrap woocommerce-card">
					<h2 class="profitly-table-title"><?php esc_html_e( 'How you compare', 'profitly' ); ?></h2>
					<table class="widefat striped profitly-planner-table">
						<caption class="screen-reader-text"><?php esc_html_e( 'Projected profit at your current pace compared with your target', 'profitly' ); ?></caption>
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Profit at your current pace', 'profitly' ); ?></th>
								<td class="profitly-num"><?php echo wp_kses_post( $profitly_money( (string) $result['projected_profit'] ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Target profit', 'profitly' ); ?></th>
								<td class="profitly-num"><?php echo wp_kses_post( $profitly_money( (string) $result['target_profit'] ) ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Profit gap', 'profitly' ); ?></th>
								<td class="profitly-num">
									<?php if ( (float) $result['profit_gap'] > 0 ) : ?>
										<?php echo wp_kses_post( $profitly_money( (string) $result['profit_gap'] ) ); ?>
									<?php else : ?>
										<?php esc_html_e( 'None — already on pace', 'profitly' ); ?>
									<?php endif; ?>
								</td>
							</tr>
						</tbody>
					</table>
					<p class="description">
						<?php
						printf(
							/* translators: 1: baseline label, e.g. "Last 90 days", 2: number of days in the planning period. */
							esc_html__( 'Your current pace projects your baseline profit (%1$s) across the %2$s days of the planning period.', 'profitly' ),
							esc_html( $baseline['label'] ),
							esc_html( number_format_i18n( (int) $result['planning_days'] ) )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $result['sensitivity'] ) ) : ?>
				<div class="profitly-table-wrap woocommerce-card">
					<h2 class="profitly-table-title"><?php esc_html_e( 'Margin sensitivity', 'profitly' ); ?></h2>
					<table class="widefat striped profitly-planner-table">
						<caption class="screen-reader-text"><?php esc_html_e( 'Revenue required at different net margins', 'profitly' ); ?></caption>
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Net margin', 'profitly' ); ?></th>
								<th scope="col" class="profitly-num"><?php esc_html_e( 'Revenue required', 'profitly' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $result['sensitivity'] as $profitly_row ) : ?>
								<tr class="<?php echo $profitly_row['is_current'] ? 'profitly-row-current' : ''; ?>">
									<th scope="row">
										<?php echo esc_html( $profitly_row['margin_percent'] . '%' ); ?>
										<?php if ( $profitly_row['is_current'] ) : ?>
											<span class="profitly-row-badge"><?php esc_html_e( 'your margin', 'profitly' ); ?></span>
										<?php endif; ?>
									</th>
									<td class="profitly-num"><?php echo wp_kses_post( $profitly_money( (string) $profitly_row['required_revenue'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description"><?php esc_html_e( 'A better margin needs less revenue to reach the same profit.', 'profitly' ); ?></p>
				</div>
			<?php endif; ?>

		</div>

		<div class="profitly-planner__disclaimer woocommerce-card">
			<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
			<p>
				<?php esc_html_e( 'This estimate is based on your historical Profitly profit margin and does not account for expenses outside Profitly’s current data, such as salaries, rent, software, taxes or financing costs.', 'profitly' ); ?>
			</p>
		</div>

	<?php endif; ?>
</div>
