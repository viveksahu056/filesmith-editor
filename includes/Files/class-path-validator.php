<?php
/**
 * Path validation and traversal protection.
 *
 * @package FileSmithEditor
 */

namespace FileSmithEditor\Files;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Path_Validator
 *
 * Every file path used by the plugin must pass through resolve().
 */
class Path_Validator {

	/**
	 * Get the real root directory of a theme or plugin.
	 *
	 * @param string $type Either 'theme' or 'plugin'.
	 * @param string $slug Theme stylesheet or plugin basename (folder/file.php).
	 * @return string|\WP_Error Normalized absolute path without trailing slash.
	 */
	public function get_root( $type, $slug ) {
		$slug = (string) $slug;

		if ( 'theme' === $type ) {
			if ( '' === $slug || basename( $slug ) !== $slug ) {
				return $this->error( 'fsed_invalid_theme', __( 'Theme not found.', 'filesmith-editor' ) );
			}
			$theme = wp_get_theme( $slug );
			if ( ! $theme->exists() ) {
				return $this->error( 'fsed_invalid_theme', __( 'Theme not found.', 'filesmith-editor' ) );
			}
			$dir = $theme->get_stylesheet_directory();
		} elseif ( 'plugin' === $type ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugins = get_plugins();
			if ( ! isset( $plugins[ $slug ] ) ) {
				return $this->error( 'fsed_invalid_plugin', __( 'Plugin not found.', 'filesmith-editor' ) );
			}
			$folder = dirname( $slug );
			if ( '.' === $folder ) {
				return $this->error( 'fsed_single_file_plugin', __( 'Single-file plugins are not supported.', 'filesmith-editor' ) );
			}
			$dir = WP_PLUGIN_DIR . '/' . $folder;
		} else {
			return $this->error( 'fsed_invalid_type', __( 'Invalid type.', 'filesmith-editor' ) );
		}

		$real = realpath( $dir );
		if ( false === $real || ! is_dir( $real ) ) {
			return $this->error( 'fsed_invalid_root', __( 'The directory could not be found.', 'filesmith-editor' ) );
		}

		return untrailingslashit( wp_normalize_path( $real ) );
	}

	/**
	 * Resolve a relative path to a safe absolute path inside the root.
	 *
	 * @param string $root       Root from get_root().
	 * @param string $relative   Path relative to the root. Empty means the root itself.
	 * @param bool   $must_exist Whether the target must already exist.
	 * @return string|\WP_Error Normalized absolute path.
	 */
	public function resolve( $root, $relative, $must_exist = true ) {
		$relative = (string) $relative;

		if ( false !== strpos( $relative, "\0" ) ) {
			return $this->error( 'fsed_invalid_path', __( 'Invalid file path.', 'filesmith-editor' ) );
		}

		$relative = trim( wp_normalize_path( $relative ), '/' );

		if ( '' !== $relative ) {
			$excluded = $this->get_excluded_folders();

			foreach ( explode( '/', $relative ) as $segment ) {
				if ( '' === $segment || '.' === $segment || '..' === $segment ) {
					return $this->error( 'fsed_invalid_path', __( 'Invalid file path.', 'filesmith-editor' ) );
				}
				if ( in_array( $segment, $excluded, true ) ) {
					return $this->error( 'fsed_excluded', __( 'This folder is excluded in the settings.', 'filesmith-editor' ) );
				}
			}
		}

		$candidate = '' === $relative ? $root : $root . '/' . $relative;

		if ( $must_exist || file_exists( $candidate ) || is_link( $candidate ) ) {
			$real = realpath( $candidate );
			if ( false === $real ) {
				return $this->error( 'fsed_not_found', __( 'File not found.', 'filesmith-editor' ) );
			}
		} else {
			$parent = realpath( dirname( $candidate ) );
			if ( false === $parent ) {
				return $this->error( 'fsed_not_found', __( 'The parent folder does not exist.', 'filesmith-editor' ) );
			}
			$real = untrailingslashit( wp_normalize_path( $parent ) ) . '/' . basename( $candidate );
		}

		$real = wp_normalize_path( $real );

		if ( ! $this->is_inside( $root, $real ) ) {
			return $this->error( 'fsed_outside_root', __( 'Access to this path is not allowed.', 'filesmith-editor' ) );
		}

		return $real;
	}

	/**
	 * Check that a path is the root or inside it.
	 *
	 * @param string $root Normalized root.
	 * @param string $path Normalized path.
	 * @return bool
	 */
	public function is_inside( $root, $path ) {
		return $path === $root || 0 === strpos( $path, $root . '/' );
	}

	/**
	 * Get the folder names that are hidden and blocked.
	 *
	 * @return string[]
	 */
	public function get_excluded_folders() {
		/**
		 * Filters the folder names hidden from the editor.
		 *
		 * @param string[] $folders Folder names. Default none. The settings page adds its own list.
		 */
		return array_map( 'strval', (array) apply_filters( 'fsed_excluded_folders', array() ) );
	}

	/**
	 * Get the list of allowed file extensions.
	 *
	 * @return string[]
	 */
	public function get_editable_extensions() {
		$extensions = array( 'php', 'css', 'scss', 'less', 'js', 'json', 'txt', 'md', 'html', 'htm', 'xml', 'svg', 'po', 'pot', 'ini', 'yml', 'yaml' );

		/**
		 * Filters the file extensions that can be edited.
		 *
		 * @param string[] $extensions Lowercase extensions without the dot.
		 */
		$extensions = apply_filters( 'fsed_editable_extensions', $extensions );

		return array_map( 'strtolower', (array) $extensions );
	}

	/**
	 * Check whether a file name or path has an allowed extension.
	 *
	 * @param string $path File name or path.
	 * @return bool
	 */
	public function is_editable_extension( $path ) {
		$extension = strtolower( pathinfo( (string) $path, PATHINFO_EXTENSION ) );
		return '' !== $extension && in_array( $extension, $this->get_editable_extensions(), true );
	}

	/**
	 * Build a WP_Error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Translated message.
	 * @return \WP_Error
	 */
	private function error( $code, $message ) {
		return new \WP_Error( $code, $message, array( 'status' => 400 ) );
	}
}
