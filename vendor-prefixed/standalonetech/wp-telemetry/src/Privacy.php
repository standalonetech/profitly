<?php
/**
 * Suggested privacy policy text.
 *
 * @package StandaloneTech\Telemetry
 *
 * @license GPL-2.0-or-later
 * Modified by profitly on 02-November-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Profitly\Vendor\StandaloneTech\Telemetry;

defined( 'ABSPATH' ) || exit;

/**
 * Privacy helpers.
 */
final class Privacy {

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
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
	}

	/**
	 * Suggest privacy policy text on Settings > Privacy.
	 */
	public function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$domain = $this->client->config( 'text_domain' );
		$host   = (string) wp_parse_url( $this->client->config( 'server_url' ), PHP_URL_HOST );

		$paragraphs = array(
			/* translators: 1: plugin name, 2: server host name. */
			sprintf( __( '%1$s sends data to %2$s only when a user deactivates the plugin and clicks "Submit & Deactivate" in the feedback popup. "Skip & Deactivate" and "Cancel" send nothing.', 'profitly' ), $this->client->config( 'name' ), $host ),
			__( 'Submitting sends the selected reason, the optional comment (up to 500 characters), the number of days the plugin was active, the plugin name and version, the WordPress and PHP versions, the WooCommerce version if active, the site language and whether the site is a multisite. Users are asked not to include personal details in the comment.', 'profitly' ),
			__( 'It does not send the site address, admin email, user names, customer data or orders. As with any web request, the receiving server can see the IP address of the server hosting this site.', 'profitly' ),
		);

		wp_add_privacy_policy_content( $this->client->config( 'name' ), wp_kses_post( wpautop( implode( "\n\n", $paragraphs ) ) ) );
	}
}
