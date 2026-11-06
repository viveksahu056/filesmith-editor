<?php
/**
 * Settings admin page.
 *
 * @package FileSmithEditor
 */

namespace FileSmithEditor\Admin;

use FileSmithEditor\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings_Page
 *
 * Uses the WordPress Settings API. The nonce and capability checks are done by
 * options.php, and Settings::sanitize() checks the capability again.
 */
class Settings_Page {

	/**
	 * Settings group name used by settings_fields().
	 *
	 * @var string
	 */
	const GROUP = 'fsed_settings_group';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( 'FileSmithEditor\Settings', 'capability' ) );
	}

	/**
	 * Register the option with the Settings API.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'FileSmithEditor\Settings', 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Settings::capability() ) ) {
			wp_die(
				esc_html__( 'You are not allowed to access this page.', 'filesmith-editor' ),
				'',
				array( 'response' => 403 )
			);
		}

		$settings = Settings::all();
		$actions  = Settings::actions();
		$themes   = Settings::themes();
		$roles    = wp_roles()->get_names();

		unset( $roles['administrator'] );

		// Roles without edit_themes and edit_plugins can never use the editor, whatever is ticked.
		$blocked_roles = array();
		foreach ( array_keys( $roles ) as $role_key ) {
			$role_object = get_role( $role_key );
			$role_caps   = $role_object ? (array) $role_object->capabilities : array();

			if ( empty( $role_caps['edit_themes'] ) && empty( $role_caps['edit_plugins'] ) ) {
				$blocked_roles[ $role_key ] = true;
			}
		}

		include FSED_PATH . 'templates/settings-page.php';
	}
}
