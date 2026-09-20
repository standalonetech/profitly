<?php
/**
 * @license GPL-2.0-or-later
 *
 * Modified by profitly on 20-September-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */
/**
 * Deactivation feedback popup (markup and asset loading).
 *
 * @package StandaloneTech\Telemetry
 */

namespace Profitly\Vendor\StandaloneTech\Telemetry;

defined( 'ABSPATH' ) || exit;

/**
 * Loads on plugins.php only, for users who can deactivate plugins, and only
 * while consent is 'yes' (with no consent there is nothing it may send).
 * Behaviour lives in assets/js/popup.js.
 */
final class Popup {

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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_footer-plugins.php', array( $this, 'render' ) );
	}

	/**
	 * The selectable reasons, code => label.
	 *
	 * @return array<string, string>
	 */
	public function reasons(): array {
		$domain  = $this->client->config( 'text_domain' );
		$default = array(
			'found_better_plugin' => __( 'I found a better plugin', 'profitly' ),
			'missing_feature'     => __( 'It is missing a feature I need', 'profitly' ),
			'not_working'         => __( 'It is not working as expected', 'profitly' ),
			'too_complex'         => __( 'It is too complex to use', 'profitly' ),
			'temporary'           => __( 'It is a temporary deactivation', 'profitly' ),
			'no_longer_needed'    => __( 'I no longer need it', 'profitly' ),
			'other'               => __( 'Other', 'profitly' ),
		);

		/**
		 * Filters the deactivation reasons for one plugin.
		 *
		 * @param array<string, string> $reasons Reason code => label.
		 * @param string                $slug    Plugin slug.
		 */
		$reasons = apply_filters( 'sts_telemetry_deactivation_reasons', $default, $this->client->slug() );
		if ( ! is_array( $reasons ) ) {
			return $default;
		}

		$clean = array();
		foreach ( $reasons as $code => $label ) {
			$code = sanitize_key( (string) $code );
			if ( '' !== $code && is_string( $label ) ) {
				$clean[ $code ] = $label;
			}
		}

		return $clean ? $clean : $default;
	}

	/**
	 * Whether the popup should load for the current user.
	 *
	 * @return bool
	 */
	private function should_load(): bool {
		return current_user_can( 'activate_plugins' )
			&& $this->client->consent()->is_granted()
			&& '' !== $this->client->assets_url();
	}

	/**
	 * Enqueue the local script and style.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue( $hook_suffix ): void {
		if ( 'plugins.php' !== $hook_suffix || ! $this->should_load() ) {
			return;
		}

		$assets = $this->client->assets_url();
		wp_enqueue_style( 'sts-telemetry-popup', $assets . 'css/popup.css', array(), Client::VERSION );
		wp_enqueue_script( 'sts-telemetry-popup', $assets . 'js/popup.js', array(), Client::VERSION, true );
	}

	/**
	 * Print the (initially hidden) dialog markup.
	 */
	public function render(): void {
		if ( ! $this->should_load() ) {
			return;
		}

		$domain  = $this->client->config( 'text_domain' );
		$slug    = $this->client->slug();
		$name    = $this->client->config( 'name' );
		$support = $this->client->config( 'support_url' );
		$action  = Ajax::deactivate_action( $slug );
		?>
		<div class="sts-telemetry-overlay" hidden
			data-plugin="<?php echo esc_attr( plugin_basename( $this->client->config( 'plugin_file' ) ) ); ?>"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-action="<?php echo esc_attr( $action ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( $action ) ); ?>">
			<div class="sts-telemetry-dialog" role="dialog" aria-modal="true"
				aria-labelledby="sts-telemetry-title-<?php echo esc_attr( $slug ); ?>"
				aria-describedby="sts-telemetry-desc-<?php echo esc_attr( $slug ); ?>">
				<h2 class="sts-telemetry-title" id="sts-telemetry-title-<?php echo esc_attr( $slug ); ?>">
					<?php
					/* translators: %s: plugin name. */
					echo esc_html( sprintf( __( 'Quick feedback about %s', 'profitly' ), $name ) );
					?>
				</h2>
				<p id="sts-telemetry-desc-<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Why are you deactivating? Your answer is optional.', 'profitly' ); ?></p>
				<fieldset class="sts-telemetry-reasons">
					<legend class="screen-reader-text"><?php esc_html_e( 'Reason for deactivating', 'profitly' ); ?></legend>
					<?php foreach ( $this->reasons() as $code => $label ) : ?>
						<label class="sts-telemetry-reason">
							<input type="radio" name="sts_telemetry_reason" value="<?php echo esc_attr( $code ); ?>" />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<?php if ( '' !== $support ) : ?>
					<p class="sts-telemetry-support" hidden>
						<a href="<?php echo esc_url( $support ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Need a hand? Contact support.', 'profitly' ); ?></a>
					</p>
				<?php endif; ?>
				<label class="sts-telemetry-text-label" for="sts-telemetry-text-<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Anything else? (optional)', 'profitly' ); ?></label>
				<textarea class="sts-telemetry-text" id="sts-telemetry-text-<?php echo esc_attr( $slug ); ?>" rows="3" maxlength="<?php echo esc_attr( (string) Collector::MAX_REASON_TEXT ); ?>"></textarea>
				<div class="sts-telemetry-buttons">
					<button type="button" class="button button-primary sts-telemetry-submit" disabled><?php esc_html_e( 'Submit & Deactivate', 'profitly' ); ?></button>
					<button type="button" class="button sts-telemetry-skip"><?php esc_html_e( 'Skip & Deactivate', 'profitly' ); ?></button>
					<button type="button" class="button sts-telemetry-cancel"><?php esc_html_e( 'Cancel', 'profitly' ); ?></button>
				</div>
				<p class="sts-telemetry-note">
					<?php esc_html_e( 'Submitting sends your reason and comment, days active, an anonymous site ID, plugin, WordPress and PHP versions and your locale. Never your site address, email or store data. Skip and Cancel send nothing.', 'profitly' ); ?>
					<a href="<?php echo esc_url( $this->client->config( 'privacy_url' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy policy', 'profitly' ); ?></a>
				</p>
			</div>
		</div>
		<?php
	}
}
