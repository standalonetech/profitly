<?php
/**
 * Shared plugin-wide constants.
 *
 * @package ProfitPress
 */

declare( strict_types=1 );

namespace ProfitPress;

defined( 'ABSPATH' ) || exit;

/**
 * Single home for strings shared across more than one component.
 *
 * Keeping the option name, its settings group, and the capability strings in
 * one place means there is exactly one spelling of each — no component invents
 * its own copy that can silently drift out of sync.
 */
final class Constants {

	/**
	 * The single wp_options row holding every ProfitPress setting.
	 */
	public const OPTION = 'profitpress_settings';

	/**
	 * The settings group passed to register_setting().
	 */
	public const OPTION_GROUP = 'profitpress_settings_group';

	/**
	 * Capability required to view reports (read-only screens).
	 */
	public const CAP_VIEW_REPORTS = 'view_woocommerce_reports';

	/**
	 * Capability required to change settings (write screens).
	 */
	public const CAP_MANAGE = 'manage_woocommerce';
}
