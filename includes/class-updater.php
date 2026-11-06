<?php
/**
 * Self-hosted updates.
 *
 * Reads a small JSON file from the author's site and plugs into the native
 * WordPress update system, so updates show on the Plugins screen and install
 * with one click. The download is verified with a SHA-256 hash before install.
 *
 * Nothing is sent to the server except a normal HTTPS request. To turn the
 * check off, add define( 'FSED_DISABLE_UPDATE_CHECK', true ); to wp-config.php.
 *
 * @package FileSmithEditor
 */

namespace FileSmithEditor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Updater
 */
class Updater {

	/**
	 * Update manifest URL.
	 *
	 * @var string
	 */
	const MANIFEST_URL = 'https://plugins.wpwebguru.com/filesmith-editor/update.json';

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	const SLUG = 'filesmith-editor';

	/**
	 * Transient that caches the manifest.
	 *
	 * @var string
	 */
	const TRANSIENT = 'fsed_update_manifest';

	/**
	 * Admin action used by the "Check for updates" link.
	 *
	 * @var string
	 */
	const ACTION = 'fsed_check_update';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		if ( self::is_disabled() ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'verify_download' ), 10, 3 );

		if ( is_admin() ) {
			add_filter( 'plugin_action_links_' . FSED_BASENAME, array( $this, 'action_links' ), 20 );
			add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_check' ) );
			add_action( 'admin_notices', array( $this, 'notice' ) );
		}
	}

	/**
	 * Whether update checks are turned off.
	 *
	 * @return bool
	 */
	public static function is_disabled() {
		$disabled = defined( 'FSED_DISABLE_UPDATE_CHECK' ) && FSED_DISABLE_UPDATE_CHECK;

		/**
		 * Filters whether the update check is disabled.
		 *
		 * @param bool $disabled Whether it is disabled.
		 */
		return (bool) apply_filters( 'fsed_disable_update_check', $disabled );
	}

	/**
	 * Get the manifest, from the cache when possible.
	 *
	 * @param bool $force Skip the cache.
	 * @return array|null Clean manifest, or null when unavailable.
	 */
	public function get_manifest( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::TRANSIENT );

			if ( is_array( $cached ) ) {
				return ! empty( $cached['version'] ) ? $cached : null;
			}
		}

		$manifest = $this->fetch();

		// Cache a failure for one hour so a down server is not hit on every page.
		set_site_transient( self::TRANSIENT, null === $manifest ? array( 'failed' => 1 ) : $manifest, null === $manifest ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS );

		return $manifest;
	}

	/**
	 * Download and validate the manifest.
	 *
	 * @return array|null
	 */
	private function fetch() {
		$response = wp_remote_get(
			self::MANIFEST_URL,
			array(
				'timeout'            => 8,
				'redirection'        => 2,
				'reject_unsafe_urls' => true,
				'sslverify'          => true,
				'headers'            => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? $this->sanitize( $data ) : null;
	}

	/**
	 * Validate and clean the manifest data.
	 *
	 * The package must be HTTPS, come from an allowed host and have a hash.
	 *
	 * @param array $data Raw data.
	 * @return array|null
	 */
	private function sanitize( array $data ) {
		$version  = isset( $data['version'] ) ? (string) $data['version'] : '';
		$package  = isset( $data['download_url'] ) ? esc_url_raw( (string) $data['download_url'], array( 'https' ) ) : '';
		$checksum = isset( $data['sha256'] ) ? strtolower( (string) $data['sha256'] ) : '';

		if ( ! preg_match( '/^\d+(\.\d+){1,3}([\-+][0-9A-Za-z.\-]+)?$/', $version ) ) {
			return null;
		}

		if ( '' === $package || ! preg_match( '/^[a-f0-9]{64}$/', $checksum ) ) {
			return null;
		}

		/**
		 * Filters the hosts the update package may be downloaded from.
		 *
		 * @param string[] $hosts Allowed hosts.
		 */
		$hosts = (array) apply_filters( 'fsed_update_hosts', array( 'plugins.wpwebguru.com', 'wpwebguru.com' ) );
		$host  = wp_parse_url( $package, PHP_URL_HOST );

		if ( ! is_string( $host ) || ! in_array( strtolower( $host ), $hosts, true ) ) {
			return null;
		}

		return array(
			'version'      => $version,
			'download_url' => $package,
			'sha256'       => $checksum,
			'requires'     => isset( $data['requires'] ) ? sanitize_text_field( (string) $data['requires'] ) : FSED_MIN_WP,
			'requires_php' => isset( $data['requires_php'] ) ? sanitize_text_field( (string) $data['requires_php'] ) : FSED_MIN_PHP,
			'tested'       => isset( $data['tested'] ) ? sanitize_text_field( (string) $data['tested'] ) : '',
			'homepage'     => isset( $data['homepage'] ) ? esc_url_raw( (string) $data['homepage'], array( 'https' ) ) : 'https://plugins.wpwebguru.com/filesmith-editor/',
			'changelog'    => isset( $data['changelog'] ) ? wp_kses_post( (string) $data['changelog'] ) : '',
			'released'     => isset( $data['released'] ) ? sanitize_text_field( (string) $data['released'] ) : '',
		);
	}

	/**
	 * Whether the manifest holds a newer version than the installed one.
	 *
	 * @param array|null $manifest Manifest.
	 * @return bool
	 */
	private function is_newer( $manifest ) {
		return is_array( $manifest ) && version_compare( $manifest['version'], FSED_VERSION, '>' );
	}

	/**
	 * Tell WordPress about the new version.
	 *
	 * @param object $transient The update_plugins transient.
	 * @return object
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$manifest = $this->get_manifest();
		$item     = (object) array(
			'id'            => 'plugins.wpwebguru.com/filesmith-editor',
			'slug'          => self::SLUG,
			'plugin'        => FSED_BASENAME,
			'new_version'   => FSED_VERSION,
			'url'           => 'https://plugins.wpwebguru.com/filesmith-editor/',
			'package'       => '',
			'icons'         => array(),
			'banners'       => array(),
			'requires'      => FSED_MIN_WP,
			'requires_php'  => FSED_MIN_PHP,
			'tested'        => '',
		);

		if ( $this->is_newer( $manifest ) ) {
			$item->new_version  = $manifest['version'];
			$item->package      = $manifest['download_url'];
			$item->url          = $manifest['homepage'];
			$item->requires     = $manifest['requires'];
			$item->requires_php = $manifest['requires_php'];
			$item->tested       = $manifest['tested'];

			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}
			$transient->response[ FSED_BASENAME ] = $item;
			unset( $transient->no_update[ FSED_BASENAME ] );
		} else {
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$transient->no_update[ FSED_BASENAME ] = $item;
			unset( $transient->response[ FSED_BASENAME ] );
		}

		return $transient;
	}

	/**
	 * Fill the "View details" popup.
	 *
	 * @param false|object|array $result Existing result.
	 * @param string             $action API action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$manifest = $this->get_manifest();

		if ( ! is_array( $manifest ) ) {
			return $result;
		}

		$sections = array(
			'description' => '<p>' . esc_html__( 'A theme and plugin file editor with tabs, quick open, PHP syntax check, backups and role-based access.', 'filesmith-editor' ) . '</p>',
		);

		if ( '' !== $manifest['changelog'] ) {
			$sections['changelog'] = $manifest['changelog'];
		}

		return (object) array(
			'name'          => 'FileSmith Editor',
			'slug'          => self::SLUG,
			'version'       => $manifest['version'],
			'author'        => '<a href="https://wpwebguru.com/">WpWebGuru</a>',
			'homepage'      => $manifest['homepage'],
			'requires'      => $manifest['requires'],
			'requires_php'  => $manifest['requires_php'],
			'tested'        => $manifest['tested'],
			'last_updated'  => $manifest['released'],
			'download_link' => $manifest['download_url'],
			'sections'      => $sections,
		);
	}

	/**
	 * Download our package and check its SHA-256 hash before WordPress installs it.
	 *
	 * @param bool|string|\WP_Error $reply    Existing reply.
	 * @param string                $package  Package URL.
	 * @param \WP_Upgrader          $upgrader Upgrader.
	 * @return bool|string|\WP_Error
	 */
	public function verify_download( $reply, $package, $upgrader ) {
		$manifest = $this->get_manifest();

		if ( ! is_array( $manifest ) || ! is_string( $package ) || $package !== $manifest['download_url'] ) {
			return $reply;
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$file = download_url( $package, 300 );

		if ( is_wp_error( $file ) ) {
			return $file;
		}

		$actual = hash_file( 'sha256', $file );

		if ( ! is_string( $actual ) || ! hash_equals( $manifest['sha256'], $actual ) ) {
			wp_delete_file( $file );
			return new \WP_Error( 'fsed_bad_hash', __( 'The downloaded update failed the integrity check and was not installed.', 'filesmith-editor' ) );
		}

		return $file;
	}

	/**
	 * Add a "Check for updates" link in the plugins list.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		if ( current_user_can( 'update_plugins' ) ) {
			$url     = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
			$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'filesmith-editor' ) . '</a>';
		}

		return $links;
	}

	/**
	 * Get the update status for display.
	 *
	 * @return array state (disabled, unknown, current or available), version, check_url, update_url.
	 */
	public function get_status() {
		$status = array(
			'state'      => 'unknown',
			'version'    => '',
			'check_url'  => '',
			'update_url' => '',
		);

		if ( self::is_disabled() ) {
			$status['state'] = 'disabled';
			return $status;
		}

		$status['check_url'] = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION . '&from=settings' ), self::ACTION );
		$manifest            = $this->get_manifest();

		if ( is_array( $manifest ) ) {
			$status['version'] = $manifest['version'];
			$status['state']   = $this->is_newer( $manifest ) ? 'available' : 'current';

			if ( 'available' === $status['state'] ) {
				$status['update_url'] = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( FSED_BASENAME ) ), 'upgrade-plugin_' . FSED_BASENAME );
			}
		}

		return $status;
	}

	/**
	 * Clear the cache, check again and return to where the check started.
	 *
	 * @return void
	 */
	public function handle_check() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'filesmith-editor' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION );

		delete_site_transient( self::TRANSIENT );
		$manifest = $this->get_manifest( true );
		wp_clean_plugins_cache( true );

		$status = null === $manifest ? 'error' : ( $this->is_newer( $manifest ) ? 'available' : 'current' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce was checked above.
		$from_settings = isset( $_GET['from'] ) && 'settings' === sanitize_key( wp_unslash( $_GET['from'] ) );
		$target        = $from_settings
			? add_query_arg( 'page', Admin\Admin_Menu::SETTINGS_SLUG, admin_url( 'admin.php' ) )
			: admin_url( 'plugins.php' );

		wp_safe_redirect( add_query_arg( 'fsed_update', $status, $target ) . ( $from_settings ? '#fsed-panel-about' : '' ) );
		exit;
	}

	/**
	 * Show the result of a manual check, and an update notice on FileSmith screens.
	 *
	 * @return void
	 */
	public function notice() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display value.
		$status = isset( $_GET['fsed_update'] ) ? sanitize_key( wp_unslash( $_GET['fsed_update'] ) ) : '';

		if ( 'current' === $status ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'FileSmith Editor is up to date.', 'filesmith-editor' ) . '</p></div>';
			return;
		}

		if ( 'error' === $status ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'FileSmith Editor could not check for updates. Try again later.', 'filesmith-editor' ) . '</p></div>';
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';

		// Show the update notice on the plugin's own screens and on the Plugins list after a manual check.
		if ( false === strpos( $id, 'fsed' ) && false === strpos( $id, 'filesmith' ) && 'dashboard' !== $id && 'available' !== $status ) {
			return;
		}

		$manifest = $this->get_manifest();

		if ( ! $this->is_newer( $manifest ) ) {
			return;
		}

		$update_url = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( FSED_BASENAME ) ), 'upgrade-plugin_' . FSED_BASENAME );

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: 1: new version, 2: installed version. */
					__( 'FileSmith Editor %1$s is available. You have %2$s.', 'filesmith-editor' ),
					$manifest['version'],
					FSED_VERSION
				)
			),
			esc_url( $update_url ),
			esc_html__( 'Update now', 'filesmith-editor' )
		);
	}
}
