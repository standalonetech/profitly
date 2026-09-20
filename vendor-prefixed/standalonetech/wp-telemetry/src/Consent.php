<?php
/**
 * @license GPL-2.0-or-later
 *
 * Modified by profitly on 20-September-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */
/**
 * Consent state, settings checkbox and the one-time admin notice.
 *
 * @package StandaloneTech\Telemetry
 */

namespace Profitly\Vendor\StandaloneTech\Telemetry;

defined( 'ABSPATH' ) || exit;

/**
 * Consent is 'yes', 'no' or unset ('' here). Unset and 'no' both mean: no requests.
 */
final class Consent {

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
		add_action( 'admin_init', array( $this, 'handle_checkbox' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
		add_action( 'admin_post_' . $this->action(), array( $this, 'handle_choice' ) );
	}

	/**
	 * Current consent: 'yes', 'no', or '' when the user has not chosen.
	 *
	 * @return string
	 */
	public function status(): string {
		$value = get_option( Client::option( $this->client->slug(), 'consent' ), '' );
		return in_array( $value, array( 'yes', 'no' ), true ) ? $value : '';
	}

	/**
	 * Whether the user has allowed telemetry.
	 *
	 * @return bool
	 */
	public function is_granted(): bool {
		return 'yes' === $this->status();
	}

	/**
	 * Anonymous site ID; '' when there is none.
	 *
	 * It exists from opt-in until the data is deleted. Withdrawing normally deletes it, so the
	 * only time it outlives consent is after a withdrawal whose server erasure failed (kept so
	 * "Delete my data" can retry). Nothing else is ever sent without consent, see Sender.
	 *
	 * @return string
	 */
	public function site_id(): string {
		return (string) get_option( Client::option( $this->client->slug(), 'site_id' ), '' );
	}

	/**
	 * Record consent, create the site ID, schedule the weekly ping, send optin.
	 */
	public function grant(): void {
		if ( $this->is_granted() ) {
			return;
		}

		$slug = $this->client->slug();
		update_option( Client::option( $slug, 'consent' ), 'yes', false );
		update_option( Client::option( $slug, 'consent_at' ), time(), false );
		update_option( Client::option( $slug, 'site_id' ), wp_generate_uuid4(), false );

		$this->client->cron()->schedule();
		$this->client->sender()->optin();
	}

	/**
	 * Record a refusal or withdrawal: delete the site ID, stop the cron. Sends nothing.
	 *
	 * @param bool $keep_site_id Keep the ID so the server copy can still be deleted later.
	 */
	public function revoke( bool $keep_site_id = false ): void {
		$slug = $this->client->slug();
		update_option( Client::option( $slug, 'consent' ), 'no', false );
		update_option( Client::option( $slug, 'consent_at' ), time(), false );
		if ( ! $keep_site_id ) {
			delete_option( Client::option( $slug, 'site_id' ) );
		}

		$this->client->cron()->unschedule();
	}

	/**
	 * Print the settings checkbox. Unchecked unless consent is 'yes'.
	 *
	 * Must sit inside an admin form; it saves itself on admin_init, with its own
	 * nonce, when that form is submitted.
	 */
	public function render_checkbox(): void {
		$domain = $this->client->config( 'text_domain' );
		$field  = $this->field();
		?>
		<label for="<?php echo esc_attr( $field ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $field . '_present' ); ?>" value="1" />
			<input type="hidden" name="<?php echo esc_attr( $field . '_nonce' ); ?>" value="<?php echo esc_attr( wp_create_nonce( $this->action() ) ); ?>" />
			<input type="checkbox" id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" value="yes" <?php checked( $this->is_granted() ); ?> />
			<?php
			/* translators: %s: plugin name. */
			echo esc_html( sprintf( __( 'Share anonymous usage data to help improve %s.', 'profitly' ), $this->client->config( 'name' ) ) );
			?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Plugin, WordPress and PHP versions and your locale. Never your site address, email or store data.', 'profitly' ); ?>
			<a href="<?php echo esc_url( $this->client->config( 'privacy_url' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy policy', 'profitly' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Save the checkbox when the host form is submitted.
	 */
	public function handle_checkbox(): void {
		$field = $this->field();
		if ( ! isset( $_POST[ $field . '_present' ] ) ) {
			return;
		}

		$nonce = isset( $_POST[ $field . '_nonce' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field . '_nonce' ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $this->action() ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$checked = isset( $_POST[ $field ] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
		if ( $checked ) {
			$this->grant();
		} elseif ( $this->is_granted() ) {
			// Unchecked while unset must stay unset: only an explicit "No thanks" or a withdrawal is a decision.
			// Withdrawing also erases what the server holds. If it cannot be reached, stop sharing anyway
			// (that right never depends on the network) but keep the ID so "Delete my data" can retry.
			if ( ! $this->client->privacy()->delete_data() ) {
				$this->revoke( true );
			}
		}
	}

	/**
	 * Show the one-time opt-in notice on the plugin's own settings screen.
	 */
	public function maybe_render_notice(): void {
		if ( '' !== $this->status() || ! current_user_can( 'manage_options' ) || ! $this->is_own_page() ) {
			return;
		}

		$domain = $this->client->config( 'text_domain' );
		$assets = $this->client->assets_url();
		$class  = 'notice notice-info sts-telemetry-notice' . ( '' !== $assets ? ' is-dismissible' : '' );

		if ( '' !== $assets ) {
			wp_enqueue_script( 'sts-telemetry-notice', $assets . 'js/notice.js', array(), Client::VERSION, true );
		}
		?>
		<div class="<?php echo esc_attr( $class ); ?>"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-action="<?php echo esc_attr( Ajax::dismiss_action( $this->client->slug() ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( Ajax::dismiss_action( $this->client->slug() ) ) ); ?>">
			<p>
				<?php
				/* translators: %s: plugin name. */
				echo esc_html( sprintf( __( 'Help improve %s? Allow it to send anonymous usage data: plugin, WordPress and PHP versions and your locale. It never sends your site address, email or store data, and nothing is sent unless you allow it.', 'profitly' ), $this->client->config( 'name' ) ) );
				?>
				<a href="<?php echo esc_url( $this->client->config( 'privacy_url' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy policy', 'profitly' ); ?></a>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $this->action() ); ?>" />
				<?php wp_nonce_field( $this->action() ); ?>
				<p>
					<button type="submit" name="choice" value="allow" class="button button-secondary"><?php esc_html_e( 'Allow', 'profitly' ); ?></button>
					<button type="submit" name="choice" value="no" class="button button-secondary"><?php esc_html_e( 'No thanks', 'profitly' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle the Allow / No thanks buttons.
	 */
	public function handle_choice(): void {
		check_admin_referer( $this->action() );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'profitly' ), '', array( 'response' => 403 ) );
		}

		$choice = isset( $_POST['choice'] ) ? sanitize_key( wp_unslash( $_POST['choice'] ) ) : '';
		// Only an explicit choice is a decision; anything else changes nothing.
		if ( 'allow' === $choice ) {
			$this->grant();
		} elseif ( 'no' === $choice ) {
			$this->revoke();
		}

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}

	/**
	 * Form field / option prefix, e.g. terawallet_tele_consent.
	 *
	 * @return string
	 */
	private function field(): string {
		return Client::option( $this->client->slug(), 'consent' );
	}

	/**
	 * Nonce and admin-post action for this plugin.
	 *
	 * @return string
	 */
	private function action(): string {
		return 'sts_telemetry_consent_' . $this->client->slug();
	}

	/**
	 * Whether the current screen is one of the plugin's own screens: any page
	 * listed in the `admin_pages` config, or the settings screen, which matches
	 * the `page` (and `tab`, if present) query args of settings_url.
	 *
	 * @return bool
	 */
	public function is_own_page(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen matching.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( '' !== $page && in_array( $page, $this->client->admin_pages(), true ) ) {
			return true;
		}

		$query = array();
		wp_parse_str( (string) wp_parse_url( $this->client->config( 'settings_url' ), PHP_URL_QUERY ), $query );
		if ( empty( $query['page'] ) ) {
			return false;
		}

		foreach ( array( 'page', 'tab' ) as $arg ) {
			if ( ! isset( $query[ $arg ] ) ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen matching.
			$current = isset( $_GET[ $arg ] ) ? sanitize_key( wp_unslash( $_GET[ $arg ] ) ) : '';
			if ( sanitize_key( $query[ $arg ] ) !== $current ) {
				return false;
			}
		}

		return true;
	}
}
