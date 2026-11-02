<?php
/**
 * Notice for when WooCommerce's product cost field is unavailable.
 *
 * @package Profitly
 */

declare( strict_types=1 );

namespace Profitly\Admin;

use Profitly\COGS\LegacyCostMigration;
use Profitly\COGS\ProductCOGS;
use Profitly\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Tells store managers when Profitly has no product costs to read.
 *
 * Always shown on Profitly's own screens. Everywhere else it can be dismissed for
 * seven days per user, because WordPress.org does not allow a nag that can't be
 * dismissed outside the plugin's own screens.
 */
final class CostSourceNotice {

	/**
	 * User meta holding the timestamp the dismissal lasts until.
	 */
	public const META_DISMISSED_UNTIL = '_profitly_cost_notice_dismissed_until';

	/**
	 * Oldest WooCommerce version Profitly supports for product costs.
	 */
	private const MIN_WC_VERSION = '10.0';

	/**
	 * Admin-post action dismissing the notice.
	 */
	private const ACTION_DISMISS = 'profitly_dismiss_cost_notice';

	/**
	 * Register WordPress hooks for this component.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION_DISMISS, array( $this, 'handle_dismiss' ) );
	}

	/**
	 * Render the notice when it applies to this user and screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Constants::CAP_MANAGE ) ) {
			return;
		}

		$outdated = defined( 'WC_VERSION' ) && version_compare( WC_VERSION, self::MIN_WC_VERSION, '<' );

		if ( ! $outdated && ProductCOGS::is_core_cogs_enabled() ) {
			return;
		}

		$own_screen = Menu::is_profitly_screen();

		if ( ! $own_screen && (int) get_user_meta( get_current_user_id(), self::META_DISMISSED_UNTIL, true ) > time() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';

		if ( $outdated ) {
			esc_html_e( 'Profitly needs WooCommerce 10.0 or newer for product costs. Please update WooCommerce.', 'profitly' );
		} else {
			esc_html_e( 'Profitly reads product costs from WooCommerce\'s built-in Cost of Goods Sold field, which is turned off. Turn it on to track profit.', 'profitly' );

			if ( LegacyCostMigration::has_legacy_costs() ) {
				echo ' ';
				esc_html_e( 'Your existing Profitly costs will be copied over automatically after you turn it on.', 'profitly' );
			}
		}

		echo '</p>';

		$actions = array();

		if ( ! $outdated ) {
			$actions[] = sprintf(
				'<a class="button button-primary" href="%1$s">%2$s</a>',
				esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=features' ) ),
				esc_html__( 'Turn on cost tracking', 'profitly' )
			);
		}

		if ( ! $own_screen ) {
			$actions[] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_DISMISS ), self::ACTION_DISMISS ) ),
				esc_html__( 'Dismiss for 7 days', 'profitly' )
			);
		}

		if ( ! empty( $actions ) ) {
			echo '<p>' . implode( ' ', $actions ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped above.
		}

		echo '</div>';
	}

	/**
	 * Dismiss the notice for the current user for seven days.
	 *
	 * @return void
	 */
	public function handle_dismiss(): void {
		if ( ! current_user_can( Constants::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to change Profitly settings.', 'profitly' ) );
		}

		check_admin_referer( self::ACTION_DISMISS );

		update_user_meta( get_current_user_id(), self::META_DISMISSED_UNTIL, time() + 7 * DAY_IN_SECONDS );

		$referer = wp_get_referer();

		wp_safe_redirect( false === $referer ? admin_url() : $referer );
		exit;
	}
}
