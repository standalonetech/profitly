<?php
/**
 * @license GPL-2.0-or-later
 *
 * Modified by profitly on 20-September-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */
/**
 * Privacy policy text and the "Delete my data" flow.
 *
 * @package StandaloneTech\Telemetry
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
		add_action( 'admin_notices', array( $this, 'maybe_render_result' ) );
		add_action( 'admin_post_' . $this->action(), array( $this, 'handle_delete' ) );
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
			sprintf( __( 'Only if you allow it, %1$s sends anonymous usage data to %2$s: a random site ID, the plugin and its version, the WordPress, PHP and WooCommerce versions, your locale and whether the site is a multisite. This is sent when you allow it and then once a week.', 'profitly' ), $this->client->config( 'name' ), $host ),
			__( 'If you deactivate the plugin and choose "Submit & Deactivate", your reason, optional comment and the number of days the plugin was active are sent as well. Choosing "Skip & Deactivate" sends nothing.', 'profitly' ),
			__( 'It never sends your site address, admin email, user names, customer data or orders. Nothing is sent unless you allow it. You can withdraw at any time in the plugin settings, which also asks the server to delete the data already sent.', 'profitly' ),
		);

		wp_add_privacy_policy_content( $this->client->config( 'name' ), wp_kses_post( wpautop( implode( "\n\n", $paragraphs ) ) ) );
	}

	/**
	 * Print the "Delete my data" button. Prints nothing when there is no data to delete.
	 */
	public function render_delete_button(): void {
		if ( '' === $this->client->consent()->site_id() ) {
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $this->action() ); ?>" />
			<?php wp_nonce_field( $this->action() ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Delete my data', 'profitly' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Ask the server to delete this site's data, then drop local telemetry state.
	 *
	 * If the server does not confirm, the site ID is kept so the user can retry.
	 *
	 * @return bool
	 */
	public function delete_data(): bool {
		if ( ! $this->client->sender()->delete() ) {
			return false;
		}

		$this->client->consent()->revoke();
		return true;
	}

	/**
	 * Handle the button.
	 */
	public function handle_delete(): void {
		check_admin_referer( $this->action() );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'profitly' ), '', array( 'response' => 403 ) );
		}

		$back = wp_get_referer();
		wp_safe_redirect( add_query_arg( $this->result_arg(), $this->delete_data() ? '1' : '0', $back ? $back : admin_url() ) );
		exit;
	}

	/**
	 * Tell the user how the deletion went.
	 */
	public function maybe_render_result(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
		if ( ! isset( $_GET[ $this->result_arg() ] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
		$ok     = '1' === sanitize_text_field( wp_unslash( $_GET[ $this->result_arg() ] ) );
		$domain = $this->client->config( 'text_domain' );
		?>
		<div class="notice <?php echo $ok ? 'notice-success' : 'notice-error'; ?> is-dismissible">
			<p><?php echo $ok ? esc_html__( 'Your usage data was deleted and sharing is now off.', 'profitly' ) : esc_html__( 'The server could not be reached, so nothing was deleted. Please try again later.', 'profitly' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Nonce and admin-post action.
	 *
	 * @return string
	 */
	private function action(): string {
		return 'sts_telemetry_delete_' . $this->client->slug();
	}

	/**
	 * Query arg carrying the deletion result.
	 *
	 * @return string
	 */
	private function result_arg(): string {
		return 'sts_telemetry_deleted_' . $this->client->slug();
	}
}
