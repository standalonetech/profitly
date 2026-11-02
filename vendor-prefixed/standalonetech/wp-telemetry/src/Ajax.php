<?php
/**
 * AJAX endpoint for the deactivation feedback.
 *
 * @package StandaloneTech\Telemetry
 *
 * @license GPL-2.0-or-later
 * Modified by profitly on 02-November-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Profitly\Vendor\StandaloneTech\Telemetry;

defined( 'ABSPATH' ) || exit;

/**
 * The handler checks nonce and capability first.
 */
final class Ajax {

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
	 * Action (and nonce) name for the deactivation feedback request.
	 *
	 * @param string $slug Plugin slug.
	 * @return string
	 */
	public static function deactivate_action( string $slug ): string {
		return 'sts_telemetry_deactivate_' . $slug;
	}

	/**
	 * Register hooks. Logged-in users only; there is no nopriv handler.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::deactivate_action( $this->client->slug() ), array( $this, 'handle_deactivate' ) );
	}

	/**
	 * Receive the popup's "Submit & Deactivate" and forward it to the server.
	 *
	 * Always answers success once authorised, whatever happens to the outgoing
	 * request: the browser deactivates the plugin regardless.
	 */
	public function handle_deactivate(): void {
		$action = self::deactivate_action( $this->client->slug() );
		if ( ! check_ajax_referer( $action, 'nonce', false ) || ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( null, 403 );
		}

		$code = isset( $_POST['reason_code'] ) ? sanitize_key( wp_unslash( $_POST['reason_code'] ) ) : '';
		$text = isset( $_POST['reason_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason_text'] ) ) : '';

		try {
			if ( isset( $this->client->popup()->reasons()[ $code ] ) ) {
				$this->client->sender()->deactivate( $code, mb_substr( $text, 0, Collector::MAX_REASON_TEXT ) );
			}
		} catch ( \Throwable $e ) {
			// Telemetry must never get in the way of deactivating.
			unset( $e );
		}

		wp_send_json_success();
	}
}
