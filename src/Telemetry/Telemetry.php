<?php
/**
 * Opt-in anonymous usage data (StandaloneTech Telemetry).
 *
 * @package Profitly
 */

declare( strict_types=1 );

namespace Profitly\Telemetry;

use Profitly\Admin\Menu;
use Profitly\Vendor\StandaloneTech\Telemetry\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Configures the prefixed telemetry client. Nothing is sent unless the store
 * owner opts in from Settings > General; see the library README.
 */
final class Telemetry {

	/**
	 * Plugin slug used by the library for every option, hook and request.
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
				'slug'         => self::SLUG,
				'name'         => 'Profitly',
				'version'      => PROFITLY_VERSION,
				'plugin_file'  => PROFITLY_FILE,
				'server_url'   => 'https://telemetry.standalonetech.com/wp-json/sts-telemetry/v1',
				'privacy_url'  => 'https://standalonetech.com/privacy-policy/',
				'settings_url' => Menu::settings_url( 'general' ),
				'admin_pages'  => array( Menu::SLUG, Menu::TARGET_SLUG, Menu::SETTINGS_SLUG ),
				'text_domain'  => 'profitly',
				'support_url'  => 'https://standalonetech.com/support/',
			)
		);
	}

	/**
	 * The initialised client, for rendering the consent checkbox and delete button.
	 *
	 * @return Client|null
	 */
	public static function client(): ?Client {
		return Client::get( self::SLUG );
	}
}
