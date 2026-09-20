<?php
/**
 * AJAX endpoints: deactivation feedback and notice dismissal.
 *
 * @package StandaloneTech\Telemetry
 *
 * @license GPL-2.0-or-later
 * Modified by profitly on 20-September-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Profitly\Vendor\StandaloneTech\Telemetry;

defined( 'ABSPATH' ) || exit;

/**
 * Every handler checks nonce and capability first.
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
	 * Action (and nonce) name for dismissing the consent notice.
	 *
	 * @param string $slug Plugin slug.
	 * @return string
	 */
	public static function dismiss_action( string $slug ): string {
		return 'sts_telemetry_dismiss_' . $slug;
	}

	/**
	 * Register hooks. Logged-in users only; there is no nopriv handler.
	 */
	public function register(): void {
		$slug = $this->client->slug();
		add_action( 'wp_ajax_' . self::deactivate_action( $slug ), array( $this, 'handle_deactivate' ) );
		add_action( 'wp_ajax_' . self::dismiss_action( $slug ), array( $this, 'handle_dismiss' ) );
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

	/**
	 * Dismissing the consent notice counts as "No thanks".
	 */
	public function handle_dismiss(): void {
		$action = self::dismiss_action( $this->client->slug() );
		if ( ! check_ajax_referer( $action, 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		if ( '' === $this->client->consent()->status() ) {
			$this->client->consent()->revoke();
		}

		wp_send_json_success();
	}
}
