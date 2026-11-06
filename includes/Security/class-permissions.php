<?php
/**
 * Capability and nonce checks.
 *
 * @package FileSmithEditor
 */

namespace FileSmithEditor\Security;

use FileSmithEditor\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Permissions
 *
 * Uses the core `edit_themes` and `edit_plugins` capabilities, so
 * DISALLOW_FILE_EDIT, DISALLOW_FILE_MODS and multisite rules are respected.
 * The plugin settings can only limit access further. They never grant more
 * than the core capabilities.
 */
class Permissions {

	/**
	 * Nonce action used by every request.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'fsed_editor_nonce';

	/**
	 * Get the capability required for a type.
	 *
	 * @param string $type Either 'theme' or 'plugin'.
	 * @return string
	 */
	public static function capability( $type ) {
		return 'theme' === $type ? 'edit_themes' : 'edit_plugins';
	}

	/**
	 * Check whether a user always has full access.
	 *
	 * Administrators, or super admins on multisite. They are never limited by
	 * the settings.
	 *
	 * @param \WP_User|null $user User. Defaults to the current user.
	 * @return bool
	 */
	public static function is_full_access_user( $user = null ) {
		$user = $user ? $user : wp_get_current_user();

		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		if ( is_multisite() ) {
			return is_super_admin( $user->ID );
		}

		return in_array( 'administrator', (array) $user->roles, true );
	}

	/**
	 * Get the actions a user is allowed to do, from the settings.
	 *
	 * The actions of all the roles of the user are combined.
	 *
	 * @param \WP_User $user User.
	 * @return string[]
	 */
	private static function allowed_actions( $user ) {
		$roles   = (array) Settings::get( 'roles' );
		$actions = array();

		foreach ( (array) $user->roles as $role ) {
			if ( isset( $roles[ $role ] ) ) {
				$actions = array_merge( $actions, (array) $roles[ $role ] );
			}
		}

		return $actions;
	}

	/**
	 * Check whether the current user may do an action, from the settings.
	 *
	 * This does not check the core capabilities. Use can_manage() for that.
	 *
	 * @param string $action Action key from Settings::actions().
	 * @return bool
	 */
	public static function can_do( $action ) {
		$user = wp_get_current_user();

		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		if ( self::is_full_access_user( $user ) ) {
			return true;
		}

		return in_array( $action, self::allowed_actions( $user ), true );
	}

	/**
	 * Check whether the current user can manage files of a type.
	 *
	 * Needs the core capability for the type and the action in the settings.
	 *
	 * @param string $type   Either 'theme' or 'plugin'.
	 * @param string $action Action key. Defaults to viewing.
	 * @return bool
	 */
	public static function can_manage( $type, $action = 'view' ) {
		if ( ! in_array( $type, array( 'theme', 'plugin' ), true ) ) {
			return false;
		}

		return current_user_can( self::capability( $type ) ) && self::can_do( $action );
	}

	/**
	 * Create the nonce passed to scripts.
	 *
	 * @return string
	 */
	public static function create_nonce() {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	/**
	 * Verify the nonce and permission of an AJAX request.
	 *
	 * The nonce is read from the `nonce` request key by check_ajax_referer().
	 *
	 * @param string $type   Either 'theme' or 'plugin'.
	 * @param string $action Action key from Settings::actions().
	 * @return true|\WP_Error
	 */
	public static function authorize( $type, $action = 'view' ) {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			return new \WP_Error(
				'fsed_invalid_nonce',
				__( 'Security check failed. Please refresh the page and try again.', 'filesmith-editor' ),
				array( 'status' => 403 )
			);
		}

		if ( ! self::can_manage( $type, $action ) ) {
			return new \WP_Error(
				'fsed_forbidden',
				self::denied_message( $type, $action ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Explain why the current user may not do an action.
	 *
	 * @param string $type   Either 'theme' or 'plugin'.
	 * @param string $action Action key from Settings::actions().
	 * @return string
	 */
	private static function denied_message( $type, $action ) {
		if ( ! in_array( $type, array( 'theme', 'plugin' ), true ) ) {
			return __( 'This request is not valid. Reload the page and try again.', 'filesmith-editor' );
		}

		if ( ! current_user_can( self::capability( $type ) ) ) {
			if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
				return __( 'File editing is turned off on this site (DISALLOW_FILE_EDIT is set in wp-config.php), so nothing can be saved.', 'filesmith-editor' );
			}

			if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
				return __( 'File changes are turned off on this site (DISALLOW_FILE_MODS is set in wp-config.php), so nothing can be saved.', 'filesmith-editor' );
			}

			if ( is_multisite() && ! is_super_admin() ) {
				return __( 'On a multisite network only super admins can change theme and plugin files.', 'filesmith-editor' );
			}

			return sprintf(
				/* translators: %s: WordPress capability name. */
				__( 'Your user role does not have the WordPress capability "%s", which is needed to change these files. Ask an administrator.', 'filesmith-editor' ),
				self::capability( $type )
			);
		}

		$actions = Settings::actions();
		$label   = isset( $actions[ $action ] ) ? $actions[ $action ] : $action;

		return sprintf(
			/* translators: %s: action name, such as Save. */
			__( 'The FileSmith access settings do not allow your account to do this (%s). Ask an administrator to allow it under FileSmith, Settings, Access control.', 'filesmith-editor' ),
			$label
		);
	}
}
