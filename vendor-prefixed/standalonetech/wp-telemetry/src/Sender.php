<?php
/**
 * The only class that makes outside requests.
 *
 * @package StandaloneTech\Telemetry
 *
 * @license GPL-2.0-or-later
 * Modified by profitly on 02-November-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Profitly\Vendor\StandaloneTech\Telemetry;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the deactivate event, the only event that exists.
 */
final class Sender {

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
	 * POST the deactivate event to {server_url}/event.
	 *
	 * Only ever called after "Submit & Deactivate", which is the user's explicit consent.
	 *
	 * @param string $reason_code Validated reason code.
	 * @param string $reason_text Optional free text.
	 * @return bool False when the request could not be started.
	 */
	public function deactivate( string $reason_code, string $reason_text ): bool {
		$payload = $this->client->collector()->collect_deactivation( $reason_code, $reason_text );
		// Added after the developer filter, so it can never be stripped.
		$payload['event_type'] = 'deactivate';

		$response = wp_remote_post(
			$this->client->config( 'server_url' ) . '/event',
			array(
				'timeout'     => 5,
				'blocking'    => false,
				'redirection' => 0,
				'sslverify'   => true,
				// The default user agent contains the site URL, which must never be sent.
				'user-agent'  => 'StandaloneTech-Telemetry/' . Client::VERSION,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode( $payload ),
			)
		);

		return ! is_wp_error( $response );
	}
}
