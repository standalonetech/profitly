<?php
/**
 * ProfitPress uninstall handler.
 *
 * Runs when the plugin is deleted from the WordPress admin. By default, no data
 * is removed — financial data should never silently vanish. Removal only happens
 * when the store owner has explicitly opted in via the General settings tab
 * (stored under `profitpress_settings['general']['delete_on_uninstall']`).
 *
 * @package ProfitPress
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Respect the opt-in flag. Default is false: leave all data intact.
$profitpress_settings = get_option( 'profitpress_settings', array() );

if ( empty( $profitpress_settings['general']['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Remove all ProfitPress product/variation meta.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_profitpress\_%'"
);

// Remove all ProfitPress order line item meta.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	"DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key LIKE '\_profitpress\_%'"
);

// Remove the plugin's own options.
delete_option( 'profitpress_settings' );
delete_option( 'profitpress_version' );
delete_option( 'profitpress_report_cache_version' );
