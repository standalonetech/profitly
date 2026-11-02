<?php
/**
 * Deactivation feedback popup (StandaloneTech Telemetry).
 *
 * @package Profitly
 */

declare( strict_types=1 );

namespace Profitly\Telemetry;

use Profitly\Vendor\StandaloneTech\Telemetry\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Configures the prefixed telemetry client. Data is sent only when the user
 * clicks "Submit & Deactivate" in the popup; see the library README.
 */
final class Telemetry {

	/**
	 * Plugin slug used by the library for every option and request.
	 */
	public const SLUG = 'profitly';

	/**
	 * Initialise the client. Must run before `admin_init`.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		Client::init(
			array(
				'slug'        => self::SLUG,
				'name'        => 'Profitly',
				'version'     => PROFITLY_VERSION,
				'plugin_file' => PROFITLY_FILE,
				'server_url'  => 'https://telemetry.standalonetech.com/wp-json/sts-telemetry/v1',
				'privacy_url' => 'https://standalonetech.com/privacy-policy/',
				'text_domain' => 'profitly',
				'support_url' => 'https://standalonetech.com/support/',
			)
		);
	}
}
