<?php
/**
 * Weekly ping schedule.
 *
 * @package StandaloneTech\Telemetry
 *
 * @license GPL-2.0-or-later
 * Modified by profitly on 20-September-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Profitly\Vendor\StandaloneTech\Telemetry;

defined( 'ABSPATH' ) || exit;

/**
 * Scheduled only while consent is 'yes'; unscheduled on opt-out and deactivation.
 */
final class Cron {

	/**
	 * Owning client.
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param Client $client Owning client.
	 */
	public function __construct( Client $client ) {
		$this->client = $client;
	}

	/**
	 * Cron hook name for a slug.
	 *
	 * @param string $slug Plugin slug.
	 * @return string
	 */
	public static function hook( string $slug ): string {
		return 'sts_telemetry_' . $slug . '_ping';
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( self::hook( $this->client->slug() ), array( $this, 'ping' ) );
		add_action( 'admin_init', array( $this, 'ensure_scheduled' ) );
		register_deactivation_hook( $this->client->config( 'plugin_file' ), array( $this, 'unschedule' ) );
	}

	/**
	 * Schedule the weekly ping if it is not already.
	 */
	public function schedule(): void {
		$hook = self::hook( $this->client->slug() );
		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time() + WEEK_IN_SECONDS, 'weekly', $hook );
		}
	}

	/**
	 * Remove the weekly ping.
	 */
	public function unschedule(): void {
		wp_clear_scheduled_hook( self::hook( $this->client->slug() ) );
	}

	/**
	 * Re-create the schedule if it was lost (e.g. after reactivation) while consent is 'yes'.
	 */
	public function ensure_scheduled(): void {
		if ( $this->client->consent()->is_granted() ) {
			$this->schedule();
		}
	}

	/**
	 * Cron callback.
	 */
	public function ping(): void {
		if ( ! $this->client->consent()->is_granted() ) {
			$this->unschedule();
			return;
		}

		$this->client->sender()->ping();
	}
}
