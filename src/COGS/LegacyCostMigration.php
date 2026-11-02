<?php
/**
 * Copies pre-1.0.4 Profitly product costs into WooCommerce's Cost field.
 *
 * @package Profitly
 */

declare( strict_types=1 );

namespace Profitly\COGS;

use Profitly\Admin\Menu;
use Profitly\Constants;
use Profitly\Reports\ReportCache;
use Throwable;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the legacy cost copy in Action Scheduler batches and reports the result.
 *
 * It only ever fills WooCommerce's empty cost fields: a cost already set in
 * WooCommerce is never overwritten, and the legacy meta is never changed, so it
 * stays available as the rollback copy.
 */
final class LegacyCostMigration {

	/**
	 * Option holding the run's state.
	 */
	public const OPTION = 'profitly_cogs_migration';

	/**
	 * Action Scheduler hook for one batch.
	 */
	public const HOOK = 'profitly_migrate_legacy_cogs';

	/**
	 * Action Scheduler group.
	 */
	private const GROUP = 'profitly';

	/**
	 * Products handled per batch.
	 */
	private const BATCH_SIZE = 50;

	/**
	 * Most product IDs kept per conflict/failed list.
	 */
	private const MAX_STORED_IDS = 200;

	/**
	 * Most edit links shown per list in the result notice.
	 */
	private const MAX_LINKS = 20;

	/**
	 * Admin-post action of the "Copy old Profitly costs again" button.
	 */
	private const ACTION_RERUN = 'profitly_rerun_cogs_migration';

	/**
	 * Admin-post action dismissing the result notice.
	 */
	private const ACTION_DISMISS = 'profitly_dismiss_cogs_migration';

	public const DECISION_SKIP     = 'skip';
	public const DECISION_COPY     = 'copy';
	public const DECISION_SAME     = 'same';
	public const DECISION_CONFLICT = 'conflict';

	/**
	 * Register WordPress hooks for this component.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( self::class, 'maybe_run' ) );
		add_action( self::HOOK, array( $this, 'run_batch' ) );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'admin_post_' . self::ACTION_RERUN, array( $this, 'handle_rerun' ) );
		add_action( 'admin_post_' . self::ACTION_DISMISS, array( $this, 'handle_dismiss' ) );
	}

	/**
	 * Decide what to do with one product. Pure: no WordPress or WooCommerce calls.
	 *
	 * Zero means "not set": the old product form saved '0' whenever the cost box was
	 * left empty. Only a plain positive decimal string counts as a legacy cost.
	 *
	 * @param string     $legacy The legacy cost.
	 * @param float|null $core   WooCommerce's current cost; null when not set.
	 * @return string One of the DECISION_* constants.
	 */
	public static function decide( string $legacy, ?float $core ): string {
		if ( 1 !== preg_match( '/^\d*\.?\d+$/', $legacy ) || 0.0 === (float) $legacy ) {
			return self::DECISION_SKIP;
		}

		if ( null === $core ) {
			return self::DECISION_COPY;
		}

		// Six places, as COGSCalculator's default scale of 2 would call 10.001 and 10.002 equal.
		$difference = COGSCalculator::subtract( $legacy, number_format( $core, 6, '.', '' ), 6 );

		return 0.0 === (float) $difference ? self::DECISION_SAME : self::DECISION_CONFLICT;
	}

	/**
	 * Start the run once the feature is on, or resume one that lost its queued batch
	 * (the feature was off for a while, or a batch died before queueing the next).
	 *
	 * @return void
	 */
	public static function maybe_run(): void {
		if ( ! ProductCOGS::is_core_cogs_enabled() || ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		$state = self::get_state();

		if ( 'done' === $state['status'] ) {
			return;
		}

		if ( 'running' === $state['status'] && as_has_scheduled_action( self::HOOK, null, self::GROUP ) ) {
			return;
		}

		$state['status'] = 'running';
		self::save_state( $state );
		self::queue_batch( (int) $state['last_id'] );
	}

	/**
	 * Process one batch and queue the next, or finish when none are left.
	 *
	 * @param int $last_id The highest product ID already handled.
	 * @return void
	 */
	public function run_batch( int $last_id ): void {
		$state = self::get_state();

		// Off mid-run: set_cogs_value() would silently do nothing while we count it as
		// copied. maybe_run() resumes from last_id once the feature is back on.
		if ( 'running' !== $state['status'] || ! ProductCOGS::is_core_cogs_enabled() ) {
			return;
		}

		$ids = self::find_batch( $last_id );

		if ( empty( $ids ) ) {
			$state['status']      = 'done';
			$state['finished_at'] = time();
			self::save_state( $state );
			ReportCache::flush();
			return;
		}

		foreach ( $ids as $id ) {
			// Saved before processing: a product that kills the request outright is
			// passed over on resume instead of being retried forever.
			$state['last_id'] = $id;
			self::save_state( $state );
			$state = self::process_product( $id, $state );
		}

		self::save_state( $state );
		self::queue_batch( (int) $state['last_id'] );
	}

	/**
	 * Whether any product still holds a real legacy cost. Zeros don't count, see decide().
	 *
	 * @return bool
	 */
	public static function has_legacy_costs(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read-only existence check, no CRUD equivalent.
		return null !== $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				WHERE p.post_type IN ( 'product', 'product_variation' )
				AND m.meta_key = %s
				AND CAST( m.meta_value AS DECIMAL(20,6) ) > 0
				LIMIT 1",
				ProductCOGS::META_COGS
			)
		);
	}

	/**
	 * Render the "Copy old Profitly costs again" form on the General settings tab.
	 *
	 * @return void
	 */
	public static function render_copy_again_button(): void {
		if ( ! self::has_legacy_costs() ) {
			return;
		}

		printf(
			'<form method="post" action="%1$s"><input type="hidden" name="action" value="%2$s" />',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( self::ACTION_RERUN )
		);
		wp_nonce_field( self::ACTION_RERUN );
		echo '<p><button type="submit" class="button">' . esc_html__( 'Copy old Profitly costs again', 'profitly' ) . '</button></p>';
		echo '<p class="description">' . esc_html__( 'Copies your old Profitly costs into WooCommerce\'s Cost field. Costs already set in WooCommerce are not changed.', 'profitly' ) . '</p>';
		echo '</form>';
	}

	/**
	 * Reset the state and run the copy again.
	 *
	 * @return void
	 */
	public function handle_rerun(): void {
		if ( ! current_user_can( Constants::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to change Profitly settings.', 'profitly' ) );
		}

		check_admin_referer( self::ACTION_RERUN );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK );
		}

		delete_option( self::OPTION );
		self::maybe_run();

		wp_safe_redirect( Menu::settings_url( 'general' ) );
		exit;
	}

	/**
	 * Remember that the result notice was dismissed.
	 *
	 * @return void
	 */
	public function handle_dismiss(): void {
		if ( ! current_user_can( Constants::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to change Profitly settings.', 'profitly' ) );
		}

		check_admin_referer( self::ACTION_DISMISS );

		$state              = self::get_state();
		$state['dismissed'] = true;
		self::save_state( $state );

		$referer = wp_get_referer();

		wp_safe_redirect( false === $referer ? Menu::settings_url() : $referer );
		exit;
	}

	/**
	 * Render the progress or result notice.
	 *
	 * @return void
	 */
	public function render_notice(): void {
		if ( ! current_user_can( Constants::CAP_MANAGE ) ) {
			return;
		}

		$state = self::get_state();

		if ( 'running' === $state['status'] ) {
			$screen = get_current_screen();

			// With the feature off the run is paused, and CostSourceNotice explains why.
			$on_screen = Menu::is_profitly_screen() || ( null !== $screen && 'edit-product' === $screen->id );

			if ( $on_screen && ProductCOGS::is_core_cogs_enabled() ) {
				echo '<div class="notice notice-info"><p>' . esc_html__( 'Profitly is copying your old product costs into WooCommerce\'s Cost field…', 'profitly' ) . '</p></div>';
			}

			return;
		}

		$counts = $state['counts'];

		// A store with nothing to copy has nothing to hear about.
		if ( 'done' !== $state['status'] || ! empty( $state['dismissed'] ) || 0 === array_sum( $counts ) ) {
			return;
		}

		$class = $counts['conflict'] > 0 || $counts['failed'] > 0 ? 'notice-warning' : 'notice-success';

		echo '<div class="notice ' . esc_attr( $class ) . '"><p>';
		printf(
			/* translators: %s: number of products. */
			esc_html( _n( 'Copied costs for %s product.', 'Copied costs for %s products.', $counts['copied'], 'profitly' ) ),
			esc_html( number_format_i18n( $counts['copied'] ) )
		);

		if ( $counts['same'] > 0 ) {
			echo ' ';
			printf(
				/* translators: %s: number of products. */
				esc_html__( '%s already matched.', 'profitly' ),
				esc_html( number_format_i18n( $counts['same'] ) )
			);
		}

		echo '</p>';

		if ( $counts['conflict'] > 0 ) {
			printf(
				'<p>%s %s</p>',
				esc_html(
					sprintf(
						/* translators: %s: number of products. */
						_n(
							'%s product already had a different cost in WooCommerce, so we kept WooCommerce\'s value — please check it:',
							'%s products already had a different cost in WooCommerce, so we kept WooCommerce\'s value — please check them:',
							$counts['conflict'],
							'profitly'
						),
						number_format_i18n( $counts['conflict'] )
					)
				),
				self::product_links( $state['conflict_ids'], $counts['conflict'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in product_links().
			);
		}

		if ( $counts['failed'] > 0 ) {
			printf(
				'<p>%s %s</p>',
				esc_html(
					sprintf(
						/* translators: %s: number of products. */
						_n(
							'%s product could not be copied:',
							'%s products could not be copied:',
							$counts['failed'],
							'profitly'
						),
						number_format_i18n( $counts['failed'] )
					)
				),
				self::product_links( $state['failed_ids'], $counts['failed'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in product_links().
			);
		}

		printf(
			'<p><a href="%1$s">%2$s</a></p></div>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_DISMISS ), self::ACTION_DISMISS ) ),
			esc_html__( 'Dismiss', 'profitly' )
		);
	}

	/**
	 * Build the edit links for a list of products. Variations link to their parent.
	 *
	 * @param array<int, int> $ids   Stored product IDs.
	 * @param int             $total How many products the list stands for in total.
	 * @return string Escaped HTML.
	 */
	private static function product_links( array $ids, int $total ): string {
		$links = array();

		foreach ( array_slice( $ids, 0, self::MAX_LINKS ) as $id ) {
			$target = wp_get_post_parent_id( $id );
			$target = $target > 0 ? $target : $id;
			$url    = get_edit_post_link( $target, 'raw' );
			$title  = esc_html( get_the_title( $target ) );

			$links[ $target ] = null === $url ? $title : '<a href="' . esc_url( $url ) . '">' . $title . '</a>';
		}

		$html = implode( ', ', $links );
		$more = $total - self::MAX_LINKS;

		if ( $more > 0 ) {
			/* translators: %s: number of further products not listed. */
			$html .= ' ' . esc_html( sprintf( __( 'and %s more', 'profitly' ), number_format_i18n( $more ) ) );
		}

		return $html;
	}

	/**
	 * Apply the decision for one product and record the outcome.
	 *
	 * @param int                  $id    Product ID.
	 * @param array<string, mixed> $state Current state.
	 * @return array<string, mixed> Updated state.
	 */
	private static function process_product( int $id, array $state ): array {
		try {
			$product = wc_get_product( $id );

			if ( ! $product instanceof WC_Product ) {
				return $state;
			}

			$raw    = $product->get_meta( ProductCOGS::META_COGS, true );
			$legacy = is_scalar( $raw ) ? (string) wc_format_decimal( (string) $raw ) : '';

			switch ( self::decide( $legacy, $product->get_cogs_value() ) ) {
				case self::DECISION_COPY:
					$product->set_cogs_value( (float) $legacy );
					$product->save();
					++$state['counts']['copied'];
					break;

				case self::DECISION_SAME:
					++$state['counts']['same'];
					break;

				case self::DECISION_CONFLICT:
					++$state['counts']['conflict'];
					$state['conflict_ids'] = self::remember_id( $state['conflict_ids'], $id );
					break;
			}
		} catch ( Throwable $e ) {
			++$state['counts']['failed'];
			$state['failed_ids'] = self::remember_id( $state['failed_ids'], $id );
		}

		return $state;
	}

	/**
	 * Add an ID to a capped list.
	 *
	 * @param array<int, int> $ids Current list.
	 * @param int             $id  ID to add.
	 * @return array<int, int>
	 */
	private static function remember_id( array $ids, int $id ): array {
		if ( count( $ids ) < self::MAX_STORED_IDS ) {
			$ids[] = $id;
		}

		return $ids;
	}

	/**
	 * Find the next batch of product IDs holding legacy meta.
	 *
	 * @param int $last_id The highest product ID already handled.
	 * @return array<int, int>
	 */
	private static function find_batch( int $last_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read-only keyset query, no CRUD equivalent for "ID greater than".
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				WHERE p.post_type IN ( 'product', 'product_variation' )
				AND m.meta_key = %s
				AND m.meta_value <> ''
				AND p.ID > %d
				ORDER BY p.ID ASC
				LIMIT %d",
				ProductCOGS::META_COGS,
				$last_id,
				self::BATCH_SIZE
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * Queue a batch.
	 *
	 * @param int $last_id The highest product ID already handled.
	 * @return void
	 */
	private static function queue_batch( int $last_id ): void {
		as_enqueue_async_action( self::HOOK, array( 'last_id' => $last_id ), self::GROUP, true );
	}

	/**
	 * Read the state, filled out with defaults. An empty status means never started.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_state(): array {
		$state = get_option( self::OPTION, array() );

		return array_replace(
			array(
				'status'       => '',
				'last_id'      => 0,
				'counts'       => array(
					'copied'   => 0,
					'same'     => 0,
					'conflict' => 0,
					'failed'   => 0,
				),
				'conflict_ids' => array(),
				'failed_ids'   => array(),
				'finished_at'  => 0,
				'dismissed'    => false,
			),
			is_array( $state ) ? $state : array()
		);
	}

	/**
	 * Store the state (not autoloaded).
	 *
	 * @param array<string, mixed> $state The state.
	 * @return void
	 */
	private static function save_state( array $state ): void {
		update_option( self::OPTION, $state, false );
	}
}
