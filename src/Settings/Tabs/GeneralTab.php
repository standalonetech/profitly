<?php
/**
 * General settings tab.
 *
 * @package Profitly
 */

declare( strict_types=1 );

namespace Profitly\Settings\Tabs;

use Profitly\Telemetry\Telemetry;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin-wide options that don't belong to a single profit input.
 *
 * Currently just the uninstall data-retention choice.
 */
final class GeneralTab implements TabInterface {

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'general';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'General', 'profitly' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $settings Current settings array.
	 * @return void
	 */
	public function render( array $settings ): void {
		$general = isset( $settings['general'] ) && is_array( $settings['general'] ) ? $settings['general'] : array();
		$checked = ! empty( $general['delete_on_uninstall'] );

		echo '<table class="form-table" role="presentation"><tbody><tr>';
		echo '<th scope="row">' . esc_html__( 'Data retention', 'profitly' ) . '</th>';
		echo '<td>';
		printf(
			'<label><input type="checkbox" name="profitly_settings[general][delete_on_uninstall]" value="1" %1$s /> %2$s</label>',
			checked( $checked, true, false ),
			esc_html__( 'Delete all Profitly data when this plugin is uninstalled', 'profitly' )
		);
		echo '<p class="description">' . esc_html__( 'When enabled, uninstalling Profitly will permanently remove all COGS data, settings, and order profit snapshots. This cannot be undone.', 'profitly' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		// Anonymous usage data: opt-in, unchecked until the user allows it.
		$telemetry = Telemetry::client();
		if ( null !== $telemetry ) {
			echo '<tr><th scope="row">' . esc_html__( 'Usage data', 'profitly' ) . '</th><td>';
			$telemetry->consent()->render_checkbox();
			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $input    Raw, unslashed submitted data.
	 * @param array<string, mixed> $existing Current full settings array.
	 * @return array<string, mixed> The sanitised general slice.
	 */
	public function sanitize( array $input, array $existing ): array {
		unset( $existing );

		$general = $input['profitly_settings']['general'] ?? array();
		$delete  = is_array( $general ) && ! empty( $general['delete_on_uninstall'] );

		return array(
			'general' => array(
				'delete_on_uninstall' => $delete,
			),
		);
	}
}
