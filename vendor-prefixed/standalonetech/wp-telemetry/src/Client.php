<?php
/**
 * Entry point: one Client per host plugin, keyed by slug.
 *
 * @package StandaloneTech\Telemetry
 *
 * @license GPL-2.0-or-later
 * Modified by profitly on 02-November-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Profitly\Vendor\StandaloneTech\Telemetry;

defined( 'ABSPATH' ) || exit;

/**
 * Holds a plugin's config and wires up the deactivation feedback components.
 *
 * Everything (options, AJAX actions) is namespaced by slug, so two plugins using
 * this library never share state.
 */
final class Client {

	public const VERSION = '2.0.0';

	private const SCHEMA = 2;

	private const REQUIRED = array( 'slug', 'name', 'version', 'plugin_file', 'server_url', 'privacy_url' );

	/**
	 * Option suffixes left behind by 1.x.
	 */
	private const LEGACY_OPTIONS = array( 'consent', 'consent_at', 'site_id' );

	/**
	 * Initialised clients, keyed by slug.
	 *
	 * @var array<string, Client>
	 */
	private static $instances = array();

	/**
	 * Normalised config.
	 *
	 * @var array<string, mixed>
	 */
	private $config;

	/**
	 * Payload builder.
	 *
	 * @var Collector
	 */
	private $collector;

	/**
	 * The only class that talks to the network.
	 *
	 * @var Sender
	 */
	private $sender;

	/**
	 * Deactivation popup.
	 *
	 * @var Popup
	 */
	private $popup;

	/**
	 * AJAX handlers.
	 *
	 * @var Ajax
	 */
	private $ajax;

	/**
	 * Privacy helpers.
	 *
	 * @var Privacy
	 */
	private $privacy;

	/**
	 * Use Client::init().
	 *
	 * @param array<string, mixed> $config Normalised config.
	 */
	private function __construct( array $config ) {
		$this->config    = $config;
		$this->collector = new Collector( $this );
		$this->sender    = new Sender( $this );
		$this->popup     = new Popup( $this );
		$this->ajax      = new Ajax( $this );
		$this->privacy   = new Privacy( $this );
	}

	/**
	 * Initialise the feedback popup for a plugin. Call once from the plugin's main file.
	 *
	 * Required keys: slug, name, version, plugin_file, server_url (https),
	 * privacy_url. Optional: text_domain (defaults to slug), support_url (shown
	 * when "not working" is chosen in the popup). The 1.x keys settings_url and
	 * admin_pages are accepted and ignored.
	 *
	 * @param array<string, mixed> $config Plugin config.
	 * @return Client|null Null when the config is invalid (nothing is hooked).
	 */
	public static function init( array $config ): ?Client {
		$config = self::normalize( $config );
		if ( null === $config ) {
			return null;
		}

		$slug = $config['slug'];
		if ( ! isset( self::$instances[ $slug ] ) ) {
			self::$instances[ $slug ] = new self( $config );
			self::$instances[ $slug ]->register();
		}

		return self::$instances[ $slug ];
	}

	/**
	 * Get an initialised client.
	 *
	 * @param string $slug Plugin slug.
	 * @return Client|null
	 */
	public static function get( string $slug ): ?Client {
		return self::$instances[ sanitize_key( $slug ) ] ?? null;
	}

	/**
	 * Remove every option for a slug, and all 1.x leftovers. Call from uninstall.php.
	 *
	 * Needs only the slug, because the plugin is not loaded during uninstall.
	 * On multisite, call it inside the plugin's per-site uninstall loop.
	 *
	 * @param string $slug Plugin slug.
	 */
	public static function cleanup( string $slug ): void {
		$slug = sanitize_key( $slug );

		self::remove_legacy( $slug );
		delete_option( self::option( $slug, 'activated_at' ) );
		delete_option( self::option( $slug, 'schema' ) );
	}

	/**
	 * Option name for a slug, e.g. terawallet_tele_activated_at.
	 *
	 * @param string $slug   Plugin slug.
	 * @param string $suffix Option suffix.
	 * @return string
	 */
	public static function option( string $slug, string $suffix ): string {
		return $slug . '_tele_' . $suffix;
	}

	/**
	 * Read a config value.
	 *
	 * @param string $key Config key.
	 * @return string
	 */
	public function config( string $key ): string {
		return (string) ( $this->config[ $key ] ?? '' );
	}

	/**
	 * Plugin slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return $this->config['slug'];
	}

	/**
	 * Payload builder.
	 *
	 * @return Collector
	 */
	public function collector(): Collector {
		return $this->collector;
	}

	/**
	 * Network sender.
	 *
	 * @return Sender
	 */
	public function sender(): Sender {
		return $this->sender;
	}

	/**
	 * Popup.
	 *
	 * @return Popup
	 */
	public function popup(): Popup {
		return $this->popup;
	}

	/**
	 * Privacy helpers (policy text).
	 *
	 * @return Privacy
	 */
	public function privacy(): Privacy {
		return $this->privacy;
	}

	/**
	 * URL of this package's assets/ directory, or '' if it is not inside the
	 * plugin directory (in which case no script/style is loaded).
	 *
	 * @return string
	 */
	public function assets_url(): string {
		$plugin_dir = wp_normalize_path( dirname( $this->config['plugin_file'] ) );
		$assets_dir = wp_normalize_path( dirname( __DIR__ ) . '/assets' );

		if ( 0 !== strpos( $assets_dir, trailingslashit( $plugin_dir ) ) ) {
			return '';
		}

		return trailingslashit( plugins_url( substr( $assets_dir, strlen( $plugin_dir ) + 1 ), $this->config['plugin_file'] ) );
	}

	/**
	 * Hook everything up. Registration only; no request is made here.
	 */
	private function register(): void {
		add_action( 'admin_init', array( $this, 'migrate' ) );
		add_action( 'admin_init', array( $this, 'stamp_activation' ) );

		$this->popup->register();
		$this->ajax->register();
		$this->privacy->register();
	}

	/**
	 * One-time removal of 1.x data from this site. Sends nothing.
	 */
	public function migrate(): void {
		$option = self::option( $this->slug(), 'schema' );
		if ( (int) get_option( $option, 0 ) >= self::SCHEMA ) {
			return;
		}

		self::remove_legacy( $this->slug() );
		update_option( $option, self::SCHEMA );
	}

	/**
	 * Remember (locally) when we first saw the plugin, for days_active.
	 */
	public function stamp_activation(): void {
		add_option( self::option( $this->slug(), 'activated_at' ), time() );
	}

	/**
	 * Remove the 1.x options and weekly ping for a slug.
	 *
	 * @param string $slug Plugin slug.
	 */
	private static function remove_legacy( string $slug ): void {
		foreach ( self::LEGACY_OPTIONS as $suffix ) {
			delete_option( self::option( $slug, $suffix ) );
		}
		wp_clear_scheduled_hook( 'sts_telemetry_' . $slug . '_ping' );
	}

	/**
	 * Validate and normalise config.
	 *
	 * @param array<string, mixed> $config Raw config.
	 * @return array<string, mixed>|null
	 */
	private static function normalize( array $config ): ?array {
		foreach ( self::REQUIRED as $key ) {
			if ( empty( $config[ $key ] ) || ! is_string( $config[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- developer notice; version is a constant.
				_doing_it_wrong( __METHOD__, sprintf( 'Missing required config key "%s"; telemetry is disabled.', esc_html( $key ) ), self::VERSION );
				return null;
			}
		}

		$config['slug'] = sanitize_key( $config['slug'] );
		if ( '' === $config['slug'] || 0 !== strpos( $config['server_url'], 'https://' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- developer notice; version is a constant.
			_doing_it_wrong( __METHOD__, 'The slug must be non-empty and server_url must use https; telemetry is disabled.', self::VERSION );
			return null;
		}

		// Passed by plugins written for 1.x; no longer used.
		unset( $config['settings_url'], $config['admin_pages'] );

		$config['server_url']  = untrailingslashit( $config['server_url'] );
		$config['text_domain'] = ! empty( $config['text_domain'] ) ? $config['text_domain'] : $config['slug'];
		$config['support_url'] = $config['support_url'] ?? '';

		return $config;
	}
}
