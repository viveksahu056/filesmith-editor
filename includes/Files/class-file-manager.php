<?php
/**
 * File operations.
 *
 * @package FileSmithEditor
 */

namespace FileSmithEditor\Files;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class File_Manager
 *
 * Lists, reads and saves files inside a theme or plugin folder. Permission and
 * nonce checks must be done by the caller before using this class.
 */
class File_Manager {

	/**
	 * Default maximum size of an editable file in bytes (2 MB).
	 *
	 * @var int
	 */
	const MAX_FILE_SIZE = 2097152;

	/**
	 * Maximum entries returned in a tree.
	 *
	 * @var int
	 */
	const MAX_ENTRIES = 5000;

	/**
	 * Default maximum size of a previewed image in bytes (5 MB).
	 *
	 * @var int
	 */
	const MAX_PREVIEW_SIZE = 5242880;

	/**
	 * Maximum folder depth returned in a tree.
	 *
	 * @var int
	 */
	const MAX_DEPTH = 10;

	/**
	 * Path validator.
	 *
	 * @var Path_Validator
	 */
	private $validator;

	/**
	 * Constructor.
	 *
	 * @param Path_Validator $validator Path validator.
	 */
	public function __construct( Path_Validator $validator ) {
		$this->validator = $validator;
	}

	/**
	 * Get the file tree of a theme or plugin.
	 *
	 * @param string $type Either 'theme' or 'plugin'.
	 * @param string $slug Theme stylesheet or plugin basename.
	 * @return array|\WP_Error
	 */
	public function get_tree( $type, $slug ) {
		$root = $this->validator->get_root( $type, $slug );
		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$fs = $this->filesystem();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}

		$count = 0;
		return $this->build_tree( $fs, $root, $root, 0, $count );
	}

	/**
	 * Read a file.
	 *
	 * @param string $type     Either 'theme' or 'plugin'.
	 * @param string $slug     Theme stylesheet or plugin basename.
	 * @param string $relative File path relative to the root.
	 * @return array|\WP_Error Array with path, content and writable keys.
	 */
	public function read_file( $type, $slug, $relative ) {
		$ctx = $this->context( $type, $slug, $relative );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}

		$path = $ctx['path'];

		if ( ! is_file( $path ) ) {
			return $this->error( 'fsed_not_file', __( 'This is not a file.', 'filesmith-editor' ) );
		}
		if ( ! $this->validator->is_editable_extension( $path ) ) {
			if ( null !== self::image_mime( $path ) ) {
				return $this->read_image( $ctx );
			}
			return $this->error( 'fsed_not_editable', __( 'This file type cannot be edited.', 'filesmith-editor' ) );
		}
		if ( filesize( $path ) > $this->get_max_file_size() ) {
			return $this->error( 'fsed_too_large', __( 'This file is too large to edit.', 'filesmith-editor' ) );
		}

		$fs = $this->filesystem();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}

		$content = $fs->get_contents( $path );
		if ( false === $content ) {
			return $this->error( 'fsed_read_failed', __( 'The file could not be read.', 'filesmith-editor' ), 500 );
		}

		return array(
			'path'     => $this->to_relative( $ctx['root'], $path ),
			'content'  => $content,
			'writable' => wp_is_writable( $path ),
		);
	}

	/**
	 * Get the image type of a file name, for the preview.
	 *
	 * SVG is not listed, because it is an editable text file.
	 *
	 * @param string $name File name or path.
	 * @return string|null MIME type, or null when the file is not a previewable image.
	 */
	public static function image_mime( $name ) {
		$types = array(
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'bmp'  => 'image/bmp',
			'ico'  => 'image/x-icon',
			'avif' => 'image/avif',
		);

		$extension = strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );

		return isset( $types[ $extension ] ) ? $types[ $extension ] : null;
	}

	/**
	 * Read an image for the preview.
	 *
	 * @param array $ctx Context from context().
	 * @return array|\WP_Error Same shape as read_file(), with an image key and no editable content.
	 */
	private function read_image( $ctx ) {
		$path = $ctx['path'];

		/**
		 * Filters the maximum size of a previewed image in bytes.
		 *
		 * @param int $size Size in bytes. Default 5 MB.
		 */
		$max = (int) apply_filters( 'fsed_max_preview_size', self::MAX_PREVIEW_SIZE );

		if ( filesize( $path ) > $max ) {
			return $this->error(
				'fsed_too_large',
				sprintf(
					/* translators: %s: size limit, such as 5 MB. */
					__( 'This image is too large to preview (limit %s).', 'filesmith-editor' ),
					size_format( $max )
				)
			);
		}

		$fs = $this->filesystem();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}

		$bytes = $fs->get_contents( $path );
		if ( false === $bytes ) {
			return $this->error( 'fsed_read_failed', __( 'The file could not be read.', 'filesmith-editor' ), 500 );
		}

		$mime = self::image_mime( $path );
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Not every image type is supported, and a broken file only means no size.
		$info = @getimagesize( $path );

		return array(
			'path'     => $this->to_relative( $ctx['root'], $path ),
			'content'  => '',
			'writable' => false,
			'image'    => array(
				'src'    => 'data:' . $mime . ';base64,' . base64_encode( $bytes ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Sends image bytes to the preview.
				'mime'   => $mime,
				'bytes'  => (int) filesize( $path ),
				'width'  => is_array( $info ) ? (int) $info[0] : 0,
				'height' => is_array( $info ) ? (int) $info[1] : 0,
			),
		);
	}

	/**
	 * Save a file.
	 *
	 * The content is code and is written as-is. Do not sanitize it.
	 *
	 * @param string $type     Either 'theme' or 'plugin'.
	 * @param string $slug     Theme stylesheet or plugin basename.
	 * @param string $relative File path relative to the root.
	 * @param string $content  New file content.
	 * @return array|\WP_Error Array with the saved path.
	 */
	public function save_file( $type, $slug, $relative, $content ) {
		$ctx = $this->context( $type, $slug, $relative );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}

		$path    = $ctx['path'];
		$content = (string) $content;

		if ( ! is_file( $path ) ) {
			return $this->error( 'fsed_not_file', __( 'The file no longer exists on the server. It may have been deleted or renamed outside the editor, so it was not saved. Use Refresh to update the file list.', 'filesmith-editor' ) );
		}
		if ( ! $this->validator->is_editable_extension( $path ) ) {
			return $this->error( 'fsed_not_editable', __( 'This file type cannot be edited.', 'filesmith-editor' ) );
		}
		if ( strlen( $content ) > $this->get_max_file_size() ) {
			return $this->error(
				'fsed_too_large',
				sprintf(
					/* translators: %s: size limit, such as 2 MB. */
					__( 'The content is larger than the editor limit of %s, so it was not saved.', 'filesmith-editor' ),
					size_format( $this->get_max_file_size() )
				)
			);
		}
		if ( ! wp_is_writable( $path ) ) {
			return $this->error(
				'fsed_not_writable',
				sprintf(
					/* translators: %s: file permissions and owner, in brackets. */
					__( 'This file is not writable by the web server%s, so it was not saved. Ask your host, or change the file permissions or owner so the web server can write to it.', 'filesmith-editor' ),
					$this->permission_hint( $path )
				),
				403
			);
		}

		/**
		 * Filters whether a file may be saved. Return a WP_Error to stop the save.
		 *
		 * Used for backups and syntax checks.
		 *
		 * @param true|\WP_Error $check   Current result.
		 * @param string         $path    Absolute file path.
		 * @param string         $content New content.
		 * @param string         $type    Either 'theme' or 'plugin'.
		 * @param string         $slug    Theme stylesheet or plugin basename.
		 */
		$check = apply_filters( 'fsed_pre_save', true, $path, $content, $type, $slug );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$fs = $this->filesystem();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}

		if ( ! $fs->put_contents( $path, $content ) ) {
			return $this->error( 'fsed_save_failed', $this->write_failure_reason( $path, strlen( $content ) ), 500 );
		}

		return array( 'path' => $this->to_relative( $ctx['root'], $path ) );
	}

	/**
	 * Build the tree of one folder.
	 *
	 * @param \WP_Filesystem_Base $fs    Filesystem.
	 * @param string              $root  Normalized root.
	 * @param string              $dir   Normalized folder to list.
	 * @param int                 $depth Current depth.
	 * @param int                 $count Running entry count.
	 * @return array
	 */
	private function build_tree( $fs, $root, $dir, $depth, &$count ) {
		$list = $fs->dirlist( $dir, false, false );
		if ( ! is_array( $list ) ) {
			return array();
		}

		$dirs     = array();
		$files    = array();
		$excluded = $this->validator->get_excluded_folders();

		foreach ( $list as $name => $info ) {
			if ( $count >= self::MAX_ENTRIES ) {
				break;
			}

			$name    = (string) $name;
			$nominal = $dir . '/' . $name;

			if ( in_array( $name, $excluded, true ) || is_link( $nominal ) ) {
				continue;
			}

			$real = realpath( $nominal );
			if ( false === $real || ! $this->validator->is_inside( $root, wp_normalize_path( $real ) ) ) {
				continue;
			}

			++$count;
			$relative = $this->to_relative( $root, $nominal );

			if ( isset( $info['type'] ) && 'd' === $info['type'] ) {
				$dirs[] = array(
					'name'     => $name,
					'path'     => $relative,
					'type'     => 'dir',
					'children' => $depth < self::MAX_DEPTH ? $this->build_tree( $fs, $root, $nominal, $depth + 1, $count ) : array(),
				);
			} else {
				$files[] = array(
					'name'     => $name,
					'path'     => $relative,
					'type'     => 'file',
					'editable' => $this->validator->is_editable_extension( $name ),
					'image'    => ! $this->validator->is_editable_extension( $name ) && null !== self::image_mime( $name ),
				);
			}
		}

		$sort = static function ( $a, $b ) {
			return strnatcasecmp( $a['name'], $b['name'] );
		};
		usort( $dirs, $sort );
		usort( $files, $sort );

		return array_merge( $dirs, $files );
	}

	/**
	 * Resolve the root and a path inside it.
	 *
	 * @param string $type       Either 'theme' or 'plugin'.
	 * @param string $slug       Theme stylesheet or plugin basename.
	 * @param string $relative   Path relative to the root.
	 * @param bool   $must_exist Whether the path must exist.
	 * @return array|\WP_Error Array with root and path.
	 */
	private function context( $type, $slug, $relative, $must_exist = true ) {
		$root = $this->validator->get_root( $type, $slug );
		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$path = $this->validator->resolve( $root, $relative, $must_exist );
		if ( is_wp_error( $path ) ) {
			return $path;
		}

		return array(
			'root' => $root,
			'path' => $path,
		);
	}

	/**
	 * Describe the permissions and owner of a file, for error messages.
	 *
	 * @param string $path Absolute file path.
	 * @return string Text such as " (permissions 0444, owner root)", or an empty string.
	 */
	private function permission_hint( $path ) {
		if ( ! file_exists( $path ) ) {
			return '';
		}

		$parts = array();
		$perms = fileperms( $path );

		if ( false !== $perms ) {
			/* translators: %s: file permissions, such as 0644. */
			$parts[] = sprintf( __( 'permissions %s', 'filesmith-editor' ), substr( sprintf( '%o', $perms ), -4 ) );
		}

		if ( function_exists( 'posix_getpwuid' ) ) {
			$info = posix_getpwuid( (int) fileowner( $path ) );

			if ( is_array( $info ) && ! empty( $info['name'] ) ) {
				/* translators: %s: user name of the file owner. */
				$parts[] = sprintf( __( 'owner %s', 'filesmith-editor' ), $info['name'] );
			}
		}

		return $parts ? ' (' . implode( ', ', $parts ) . ')' : '';
	}

	/**
	 * Explain why writing a file failed.
	 *
	 * @param string $path   Absolute file path.
	 * @param int    $length Number of bytes that should have been written.
	 * @return string
	 */
	private function write_failure_reason( $path, $length ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Can warn when open_basedir blocks the check.
		$free = function_exists( 'disk_free_space' ) ? @disk_free_space( dirname( $path ) ) : false;

		if ( false !== $free && $free < $length + 1024 ) {
			return __( 'The server is out of disk space, so the file was not saved.', 'filesmith-editor' );
		}

		return __( 'The server refused to write the file, so it was not saved. Common reasons: a full disk quota, a security setting that blocks writing (open_basedir, SELinux or a read-only file system), or a host that locks files. Check the PHP error log or ask your host.', 'filesmith-editor' );
	}

	/**
	 * Get the WordPress filesystem object.
	 *
	 * @return \WP_Filesystem_Base|\WP_Error
	 */
	private function filesystem() {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( empty( $wp_filesystem ) || ! is_object( $wp_filesystem ) ) {
			WP_Filesystem();
		}

		if ( empty( $wp_filesystem ) || ! is_object( $wp_filesystem ) ) {
			return $this->error( 'fsed_no_filesystem', __( 'WordPress cannot write files directly on this server (it asks for FTP details instead), so nothing can be saved. Add define( \'FS_METHOD\', \'direct\' ); to wp-config.php, or fix the file owner so the web server owns the files.', 'filesmith-editor' ), 500 );
		}

		return $wp_filesystem;
	}

	/**
	 * Get the maximum editable file size.
	 *
	 * @return int
	 */
	private function get_max_file_size() {
		/**
		 * Filters the maximum size of an editable file in bytes.
		 *
		 * @param int $size Size in bytes.
		 */
		return (int) apply_filters( 'fsed_max_file_size', self::MAX_FILE_SIZE );
	}

	/**
	 * Convert an absolute path to a path relative to the root.
	 *
	 * @param string $root Normalized root.
	 * @param string $path Normalized absolute path.
	 * @return string
	 */
	private function to_relative( $root, $path ) {
		return ltrim( substr( $path, strlen( $root ) ), '/' );
	}

	/**
	 * Build a WP_Error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Translated message.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error
	 */
	private function error( $code, $message, $status = 400 ) {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
