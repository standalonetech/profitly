<?php
/**
 * The only class that makes outside requests.
 *
 * @package StandaloneTech\Telemetry
 *
 * @license GPL-2.0-or-later
 * Modified by profitly on 20-September-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Profitly\Vendor\StandaloneTech\Telemetry;

defined( 'ABSPATH' ) || exit;

/**
 * Every request funnels through send(), which refuses unless consent is 'yes'.
 */
final class Sender {

	private const EVENTS = array( 'optin', 'ping', 'deactivate', 'delete' );

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
	 * Send the optin event.
	 *
	 * @return bool
	 */
	public function optin(): bool {
		return $this->send( 'optin', $this->client->collector()->collect( 'optin' ) );
	}

	/**
	 * Send the weekly ping.
	 *
	 * @return bool
	 */
	public function ping(): bool {
		return $this->send( 'ping', $this->client->collector()->collect( 'ping' ) );
	}

	/**
	 * Send the deactivate event (only ever called after "Submit & Deactivate").
	 *
	 * @param string $reason_code Validated reason code.
	 * @param string $reason_text Optional free text.
	 * @return bool
	 */
	public function deactivate( string $reason_code, string $reason_text ): bool {
		return $this->send( 'deactivate', $this->client->collector()->collect_deactivation( $reason_code, $reason_text ) );
	}

	/**
	 * Ask the server to delete this site's data. Blocking, so the caller knows it worked.
	 *
	 * @return bool True when the server confirmed with a 2xx.
	 */
	public function delete(): bool {
		$payload = $this->client->collector()->collect( 'delete' );
		if ( empty( $payload['site_id'] ) ) {
			return false;
		}

		return $this->send( 'delete', $payload, true );
	}

	/**
	 * POST a payload to {server_url}/event (with event_type), or {server_url}/delete.
	 *
	 * @param string               $event    Event name.
	 * @param array<string, mixed> $payload  Payload.
	 * @param bool                 $blocking Wait for the response.
	 * @return bool False when refused (no consent) or the request failed.
	 */
	private function send( string $event, array $payload, bool $blocking = false ): bool {
		if ( ! in_array( $event, self::EVENTS, true ) || ! $this->client->consent()->is_granted() ) {
			return false;
		}

		$route = 'delete' === $event ? 'delete' : 'event';
		if ( 'delete' !== $event ) {
			// Added after the developer filter, so it can never be stripped.
			$payload['event_type'] = $event;
		}

		$response = wp_remote_post(
			$this->client->config( 'server_url' ) . '/' . $route,
			array(
				'timeout'     => 5,
				'blocking'    => $blocking,
				'redirection' => 0,
				'sslverify'   => true,
				// The default user agent contains the site URL, which must never be sent.
				'user-agent'  => 'StandaloneTech-Telemetry/' . Client::VERSION,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( ! $blocking ) {
			return true;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}
}
