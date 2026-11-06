<?php
/**
 * Plugin settings: defaults, reading and sanitizing.
 *
 * @package FileSmithEditor
 */

namespace FileSmithEditor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 *
 * All settings live in one option. sanitize() can run more than once on the
 * same value (WordPress does this on the first save), so it accepts both the
 * form input and its own output.
 */
final class Settings {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION = 'fsed_settings';

	/**
	 * Get the actions that access can be granted for.
	 *
	 * @return array Action => label.
	 */
	public static function actions() {
		return array(
			'view'    => __( 'View', 'filesmith-editor' ),
			'save'    => __( 'Edit and save', 'filesmith-editor' ),
			'backups' => __( 'Backups', 'filesmith-editor' ),
		);
	}

	/**
	 * Get the editor themes.
	 *
	 * @return array Theme => label.
	 */
	public static function themes() {
		return array(
			'light' => __( 'Light', 'filesmith-editor' ),
			'dark'  => __( 'Dark', 'filesmith-editor' ),
		);
	}

	/**
	 * Get the default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'max_backups'      => 5,
			'extensions'       => array( 'php', 'css', 'scss', 'less', 'js', 'json', 'txt', 'md', 'html', 'htm', 'xml', 'svg', 'po', 'pot', 'ini', 'yml', 'yaml' ),
			'excluded_folders' => array( '.git', '.svn', 'node_modules' ),
			'font_size'        => 13,
			'editor_theme'     => 'light',
			'word_wrap'        => false,
			'line_numbers'     => true,
			'tab_size'         => 4,
			'roles'            => array(),
		);
	}

	/**
	 * Get all settings, with defaults for missing values.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed Null when the key does not exist.
	 */
	public static function get( $key ) {
		$all = self::all();

		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Get the capability needed to change the settings.
	 *
	 * On multisite only super admins can change them, because they decide who
	 * may edit code.
	 *
	 * @return string
	 */
	public static function capability() {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * Apply the settings through the plugin filters.
	 *
	 * Runs at priority 5, so code that uses the filters at the default priority
	 * can still override a setting.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_filter(
			'fsed_editable_extensions',
			static function () {
				return (array) Settings::get( 'extensions' );
			},
			5
		);

		add_filter(
			'fsed_max_backups',
			static function () {
				return (int) Settings::get( 'max_backups' );
			},
			5
		);

		add_filter(
			'fsed_excluded_folders',
			static function ( $folders ) {
				return array_merge( (array) $folders, (array) Settings::get( 'excluded_folders' ) );
			},
			5
		);
	}

	/**
	 * Sanitize the settings before they are saved.
	 *
	 * @param mixed $input Form input, or an already sanitized value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$old = self::all();

		if ( ! is_array( $input ) || ! current_user_can( self::capability() ) ) {
			return $old;
		}

		$out = array();

		$out['max_backups'] = self::clamp( isset( $input['max_backups'] ) ? $input['max_backups'] : $old['max_backups'], 1, 5 );
		$out['font_size']   = self::clamp( isset( $input['font_size'] ) ? $input['font_size'] : $old['font_size'], 10, 24 );

		$out['word_wrap']    = isset( $input['word_wrap'] ) ? (bool) $input['word_wrap'] : (bool) $old['word_wrap'];
		$out['line_numbers'] = isset( $input['line_numbers'] ) ? (bool) $input['line_numbers'] : (bool) $old['line_numbers'];

		$tab_size          = isset( $input['tab_size'] ) ? (int) $input['tab_size'] : (int) $old['tab_size'];
		$out['tab_size']   = in_array( $tab_size, array( 2, 4, 8 ), true ) ? $tab_size : 4;

		$theme               = isset( $input['editor_theme'] ) ? sanitize_key( $input['editor_theme'] ) : $old['editor_theme'];
		$out['editor_theme'] = isset( self::themes()[ $theme ] ) ? $theme : 'light';

		$extensions = array();
		foreach ( self::to_list( isset( $input['extensions'] ) ? $input['extensions'] : $old['extensions'] ) as $extension ) {
			$extension = ltrim( strtolower( $extension ), '.' );
			if ( preg_match( '/^[a-z0-9]{1,10}$/', $extension ) ) {
				$extensions[] = $extension;
			}
		}
		$extensions        = array_values( array_unique( $extensions ) );
		$out['extensions'] = $extensions ? $extensions : self::defaults()['extensions'];

		$folders = array();
		foreach ( self::to_list( isset( $input['excluded_folders'] ) ? $input['excluded_folders'] : $old['excluded_folders'] ) as $folder ) {
			if ( preg_match( '/^[A-Za-z0-9._-]{1,100}$/', $folder ) && ! in_array( $folder, array( '.', '..' ), true ) ) {
				$folders[] = $folder;
			}
		}
		$out['excluded_folders'] = array_values( array_unique( $folders ) );

		$out['roles'] = self::sanitize_roles( isset( $input['roles'] ) ? $input['roles'] : array() );

		return $out;
	}

	/**
	 * Sanitize the role permissions.
	 *
	 * Administrators always have full access, so they are not stored.
	 *
	 * @param mixed $input Role => actions.
	 * @return array Role => actions. Roles without access are left out.
	 */
	private static function sanitize_roles( $input ) {
		$input = is_array( $input ) ? $input : array();
		$roles = array();

		foreach ( array_keys( wp_roles()->get_names() ) as $role ) {
			if ( 'administrator' === $role || ! isset( $input[ $role ] ) ) {
				continue;
			}
			$actions = self::clean_actions( $input[ $role ] );
			if ( $actions ) {
				$roles[ $role ] = $actions;
			}
		}

		return $roles;
	}

	/**
	 * Keep only valid actions. "view" is added when any action is set.
	 *
	 * @param mixed $value List of action keys.
	 * @return string[]
	 */
	private static function clean_actions( $value ) {
		$value   = array_map( 'sanitize_key', array_filter( (array) $value, 'is_string' ) );
		$actions = array_values( array_intersect( array_keys( self::actions() ), $value ) );

		if ( $actions && ! in_array( 'view', $actions, true ) ) {
			array_unshift( $actions, 'view' );
		}

		return $actions;
	}

	/**
	 * Turn a string (split by commas, spaces or new lines) or an array into a list.
	 *
	 * @param mixed $value String or array.
	 * @return string[]
	 */
	private static function to_list( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value );
		}

		return array_values( array_filter( array_map( 'trim', array_filter( (array) $value, 'is_string' ) ), 'strlen' ) );
	}

	/**
	 * Cast a value to an integer inside a range.
	 *
	 * @param mixed $value Value.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 * @return int
	 */
	private static function clamp( $value, $min, $max ) {
		return max( $min, min( $max, (int) $value ) );
	}
}
