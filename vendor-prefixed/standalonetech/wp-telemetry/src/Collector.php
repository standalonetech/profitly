<?php
/**
 * Builds the (whitelisted) telemetry payloads.
 *
 * @package StandaloneTech\Telemetry
 *
 * @license GPL-2.0-or-later
 * Modified by profitly on 20-September-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Profitly\Vendor\StandaloneTech\Telemetry;

defined( 'ABSPATH' ) || exit;

/**
 * The only place a payload is assembled. Nothing outside the whitelist below
 * can reach the wire: not site URL, admin email, users, orders or profit data.
 */
final class Collector {

	public const MAX_REASON_TEXT = 500;

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
	 * Payload for the optin and ping events, or the delete request.
	 *
	 * @param string $event One of optin, ping, delete.
	 * @return array<string, mixed>
	 */
	public function collect( string $event ): array {
		$data = $this->base();

		if ( 'delete' === $event ) {
			// Erasure must always work, so the developer filter does not apply here.
			return array_intersect_key(
				$data,
				array(
					'site_id'     => 1,
					'plugin_slug' => 1,
				)
			);
		}

		return $this->finalize( $event, $data );
	}

	/**
	 * Payload for the deactivate event.
	 *
	 * @param string $reason_code Reason code, already validated by the caller.
	 * @param string $reason_text Optional free text.
	 * @return array<string, mixed>
	 */
	public function collect_deactivation( string $reason_code, string $reason_text ): array {
		$data                = $this->base();
		$data['reason_code'] = $reason_code;
		$data['reason_text'] = mb_substr( $reason_text, 0, self::MAX_REASON_TEXT );
		$data['days_active'] = $this->days_active();

		return $this->finalize( 'deactivate', $data );
	}

	/**
	 * The fields common to every event.
	 *
	 * @return array<string, mixed>
	 */
	private function base(): array {
		$data    = array();
		$site_id = $this->client->consent()->site_id();

		if ( '' !== $site_id ) {
			$data['site_id'] = $site_id;
		}

		$data['plugin_slug']    = $this->client->slug();
		$data['plugin_version'] = $this->client->config( 'version' );
		$data['wp_version']     = get_bloginfo( 'version' );
		// Not PHP_VERSION: distro builds append text ("8.2.10-2+ubuntu22.04.1+deb.sury.org+1") that fingerprints the host.
		$data['php_version'] = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;

		if ( defined( 'WC_VERSION' ) ) {
			$data['wc_version'] = WC_VERSION;
		}

		$data['locale']       = get_locale();
		$data['is_multisite'] = is_multisite();

		return $data;
	}

	/**
	 * Apply the developer filter, which may remove fields but never add or change them.
	 *
	 * @param string               $event Event name.
	 * @param array<string, mixed> $data  Payload.
	 * @return array<string, mixed>
	 */
	private function finalize( string $event, array $data ): array {
		/**
		 * Filters the telemetry payload so a site developer can REMOVE fields.
		 *
		 * Keys added by the callback, or changed values, are discarded: only the
		 * keys that remain are sent, with their original values.
		 *
		 * @param array<string, mixed> $data  Payload.
		 * @param string               $event Event: optin, ping or deactivate.
		 * @param string               $slug  Plugin slug.
		 */
		$filtered = apply_filters( 'sts_telemetry_data', $data, $event, $this->client->slug() );

		return is_array( $filtered ) ? array_intersect_key( $data, $filtered ) : $data;
	}

	/**
	 * Whole days since the plugin was first seen on this site.
	 *
	 * @return int
	 */
	private function days_active(): int {
		$since = (int) get_option( Client::option( $this->client->slug(), 'activated_at' ), 0 );
		return $since > 0 ? max( 0, (int) floor( ( time() - $since ) / DAY_IN_SECONDS ) ) : 0;
	}
}
