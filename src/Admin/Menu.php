<?php
/**
 * Top-level ProfitPress admin menu.
 *
 * @package ProfitPress
 */

declare( strict_types=1 );

namespace ProfitPress\Admin;

use ProfitPress\Constants;
use ProfitPress\Reports\ReportsPage;
use ProfitPress\Settings\SettingsPage;

defined( 'ABSPATH' ) || exit;

/**
 * The single source of truth for ProfitPress admin navigation.
 *
 * It registers the top-level "ProfitPress" menu and its Reports and Settings
 * sub-pages, and exposes the canonical URL helpers every other component uses to
 * link to those pages. No other code should construct these URLs by hand.
 */
final class Menu {

	/**
	 * Menu slug of the top-level page (also the Reports sub-page slug).
	 */
	public const SLUG = 'profitpress';

	/**
	 * Menu slug of the Settings sub-page.
	 */
	public const SETTINGS_SLUG = 'profitpress-settings';

	/**
	 * Register WordPress hooks for this component.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 10 );
	}

	/**
	 * Register the top-level menu and its sub-pages.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'ProfitPress', 'profitpress' ),
			__( 'ProfitPress', 'profitpress' ),
			Constants::CAP_VIEW_REPORTS,
			self::SLUG,
			static function (): void {
				( new ReportsPage() )->render();
			},
			'dashicons-chart-area',
			56
		);

		// Rename the auto-created first sub-item from "ProfitPress" to "Reports"
		// by re-registering it against the same slug as the parent. The callback
		// is intentionally empty: this submenu shares the parent's page hook
		// (toplevel_page_profitpress), and the parent's callback already renders
		// it. Passing a second (distinct) callback here would hook the same page
		// twice and render the report — stats and all — twice.
		add_submenu_page(
			self::SLUG,
			__( 'Reports', 'profitpress' ),
			__( 'Reports', 'profitpress' ),
			Constants::CAP_VIEW_REPORTS,
			self::SLUG,
			''
		);

		add_submenu_page(
			self::SLUG,
			__( 'Settings', 'profitpress' ),
			__( 'Settings', 'profitpress' ),
			Constants::CAP_MANAGE,
			self::SETTINGS_SLUG,
			array( SettingsPage::class, 'render' )
		);
	}

	/**
	 * Canonical URL of the Reports page.
	 *
	 * @return string
	 */
	public static function reports_url(): string {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/**
	 * Canonical URL of the Settings page, optionally for a specific tab.
	 *
	 * @param string $tab Optional tab id.
	 * @return string
	 */
	public static function settings_url( string $tab = '' ): string {
		$url = admin_url( 'admin.php?page=' . self::SETTINGS_SLUG );

		if ( '' !== $tab ) {
			$url = add_query_arg( 'tab', $tab, $url );
		}

		return $url;
	}
}
