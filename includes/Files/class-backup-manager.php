<?php
/**
 * File backups.
 *
 * @package FileSmithEditor
 */

namespace FileSmithEditor\Files;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Backup_Manager
 *
 * Copies the current content of a file before every save. Backups live in an
 * unguessable folder inside uploads, with a .bak extension so they are never
 * executed, plus index.php and .htaccess guard files.
 */
class Backup_Manager {

	/**
	 * Option that stores the backup folder name.
	 *
	 * @var string
	 */
	const OPTION_DIR = 'fsed_backup_dir';

	/**
	 * Pattern of a valid backup id.
	 *
	 * @var string
	 */
	const ID_PATTERN = '/^\d{8}-\d{6}-[A-Za-z0-9]{6}\.bak$/';

	/**
	 * Default number of backups kept per file.
	 *
	 * @var int
	 */
	const DEFAULT_MAX = 5;

	/**
	 * Most backups kept per file in the free version.
	 *
	 * @var int
	 */
	const LIMIT = 5;

	/**
	 * Path validator.
	 *
	 * @var Path_Validator
	 */
	private $validator;

	/**
	 * Constructor.
	 *
	 * @param Path_Validator|null $validator Path validator.
	 */
	public function __construct( ?Path_Validator $validator = null ) {
		$this->validator = $validator ? $validator : new Path_Validator();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'fsed_pre_save', array( $this, 'filter_pre_save' ), 20, 5 );
	}

	/**
	 * Back up the file before it is saved.
	 *
	 * @param true|\WP_Error $check   Current result.
	 * @param string         $path    Absolute file path.
	 * @param string         $content New content.
	 * @param string         $type    Either 'theme' or 'plugin'.
	 * @param string         $slug    Theme stylesheet or plugin basename.
	 * @return true|\WP_Error
	 */
	public function filter_pre_save( $check, $path, $content, $type, $slug ) {
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$fs = $this->filesystem();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}

		$current = $fs->get_contents( $path );
		if ( false === $current ) {
			return $this->error( __( 'Could not read the current file to back it up, so it was not saved. Check the file permissions.', 'filesmith-editor' ) );
		}

		// Nothing changes, so there is nothing to back up.
		if ( $current === $content ) {
			return $check;
		}

		$created = $this->create( $path, $current );

		/**
		 * Filters whether a failed backup stops the save.
		 *
		 * @param bool   $required Whether a backup is required. Default true.
		 * @param string $path     Absolute file path.
		 */
		if ( is_wp_error( $created ) && apply_filters( 'fsed_require_backup', true, $path ) ) {
			return $created;
		}

		return $check;
	}

	/**
	 * Create a backup.
	 *
	 * @param string $path    Absolute path of the original file.
	 * @param string $content Content to store.
	 * @return string|\WP_Error Backup id.
	 */
	public function create( $path, $content ) {
		$fs = $this->filesystem();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}

		$dir = $this->get_file_dir( $path, true );
		if ( '' === $dir ) {
			return $this->error( __( 'Could not create the backup folder, so the file was not saved. Make sure the WordPress uploads folder is writable.', 'filesmith-editor' ) );
		}

		$id = gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ) . '.bak';

		if ( ! $fs->put_contents( $dir . $id, (string) $content ) ) {
			return $this->error( __( 'Could not create a backup, so the file was not saved. Check the free disk space and that the WordPress uploads folder is writable.', 'filesmith-editor' ) );
		}

		$this->write_meta( $dir, $id );
		$this->prune( $dir );

		return $id;
	}

	/**
	 * Get the backups of a file, newest first.
	 *
	 * @param string $path Absolute path of the original file.
	 * @return array[] List of arrays with id, time and size.
	 */
	public function get_backups( $path ) {
		return $this->scan( $this->get_file_dir( $path, false ) );
	}

	/**
	 * Read the content of a backup.
	 *
	 * @param string $path Absolute path of the original file.
	 * @param string $id   Backup id.
	 * @return string|\WP_Error
	 */
	public function read( $path, $id ) {
		if ( ! preg_match( self::ID_PATTERN, (string) $id ) ) {
			return $this->error( __( 'Invalid backup.', 'filesmith-editor' ) );
		}

		$dir = $this->get_file_dir( $path, false );
		if ( '' === $dir || ! is_file( $dir . $id ) ) {
			return $this->error( __( 'Backup not found.', 'filesmith-editor' ) );
		}

		$fs = $this->filesystem();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}

		$content = $fs->get_contents( $dir . $id );
		if ( false === $content ) {
			return $this->error( __( 'The backup could not be read.', 'filesmith-editor' ) );
		}

		return $content;
	}

	/**
	 * List the backups of a file inside a theme or plugin, for display.
	 *
	 * @param string $type     Either 'theme' or 'plugin'.
	 * @param string $slug     Theme stylesheet or plugin basename.
	 * @param string $relative File path relative to the root.
	 * @return array|\WP_Error Array with a backups list of id, label, size and author.
	 */
	public function list_for( $type, $slug, $relative ) {
		$path = $this->resolve_file( $type, $slug, $relative );
		if ( is_wp_error( $path ) ) {
			return $path;
		}

		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$dir         = $this->get_file_dir( $path, false );
		$items       = array();

		foreach ( $this->get_backups( $path ) as $backup ) {
			$items[] = array(
				'id'     => $backup['id'],
				'label'  => wp_date( $date_format, $backup['time'] ),
				'size'   => (string) size_format( $backup['size'] ),
				'author' => $this->read_meta( $dir, $backup['id'] ),
			);
		}

		return array( 'backups' => $items );
	}

	/**
	 * Read a backup of a file inside a theme or plugin.
	 *
	 * @param string $type     Either 'theme' or 'plugin'.
	 * @param string $slug     Theme stylesheet or plugin basename.
	 * @param string $relative File path relative to the root.
	 * @param string $id       Backup id.
	 * @return array|\WP_Error Array with id and content.
	 */
	public function read_for( $type, $slug, $relative, $id ) {
		$path = $this->resolve_file( $type, $slug, $relative );
		if ( is_wp_error( $path ) ) {
			return $path;
		}

		$content = $this->read( $path, $id );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		return array(
			'id'      => (string) $id,
			'content' => $content,
		);
	}

	/**
	 * Resolve and validate an editable file.
	 *
	 * @param string $type     Either 'theme' or 'plugin'.
	 * @param string $slug     Theme stylesheet or plugin basename.
	 * @param string $relative File path relative to the root.
	 * @return string|\WP_Error Absolute normalized path.
	 */
	private function resolve_file( $type, $slug, $relative ) {
		$root = $this->validator->get_root( $type, $slug );
		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$path = $this->validator->resolve( $root, $relative );
		if ( is_wp_error( $path ) ) {
			return $path;
		}

		if ( ! is_file( $path ) || ! $this->validator->is_editable_extension( $path ) ) {
			return $this->error( __( 'This file has no backups.', 'filesmith-editor' ), 400 );
		}

		return $path;
	}

	/**
	 * Get the name of the file that stores the details of a backup.
	 *
	 * @param string $id Valid backup id.
	 * @return string
	 */
	private function meta_name( $id ) {
		return substr( $id, 0, -4 ) . '.json';
	}

	/**
	 * Store who created a backup, next to the backup file.
	 *
	 * A failure here never stops a save, because the backup itself already exists.
	 *
	 * @param string $dir Backup folder of one file.
	 * @param string $id  Backup id.
	 * @return void
	 */
	private function write_meta( $dir, $id ) {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return;
		}

		$name = '' !== $user->display_name ? $user->display_name : $user->user_login;

		$fs = $this->filesystem();
		if ( is_wp_error( $fs ) ) {
			return;
		}

		$fs->put_contents( $dir . $this->meta_name( $id ), wp_json_encode( array( 'name' => $name ) ) );
	}

	/**
	 * Read the name of the user who created a backup.
	 *
	 * @param string $dir Backup folder of one file.
	 * @param string $id  Backup id.
	 * @return string Display name, or an empty string for backups made before names were recorded.
	 */
	private function read_meta( $dir, $id ) {
		if ( '' === $dir ) {
			return '';
		}

		$file = $dir . $this->meta_name( $id );
		if ( ! is_file( $file ) ) {
			return '';
		}

		$fs = $this->filesystem();
		if ( is_wp_error( $fs ) ) {
			return '';
		}

		$data = json_decode( (string) $fs->get_contents( $file ), true );
		if ( ! is_array( $data ) || empty( $data['name'] ) || ! is_string( $data['name'] ) ) {
			return '';
		}

		return mb_substr( sanitize_text_field( $data['name'] ), 0, 100 );
	}

	/**
	 * Delete the oldest backups of a file above the limit.
	 *
	 * @param string $dir Backup folder of one file.
	 * @return void
	 */
	private function prune( $dir ) {
		/**
		 * Filters how many backups are kept per file.
		 *
		 * @param int $max Number of backups. Default 5, and never more than 5 in the free version.
		 */
		$max = min( self::LIMIT, (int) apply_filters( 'fsed_max_backups', self::DEFAULT_MAX ) );
		if ( $max < 1 ) {
			return;
		}

		$fs = $this->filesystem();
		if ( is_wp_error( $fs ) ) {
			return;
		}

		foreach ( array_slice( $this->scan( $dir ), $max ) as $old ) {
			$fs->delete( $dir . $old['id'], false, 'f' );
			$fs->delete( $dir . $this->meta_name( $old['id'] ), false, 'f' );
		}
	}

	/**
	 * List the backups in a folder, newest first.
	 *
	 * @param string $dir Backup folder of one file.
	 * @return array[] List of arrays with id, time and size.
	 */
	private function scan( $dir ) {
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return array();
		}

		$fs = $this->filesystem();
		if ( is_wp_error( $fs ) ) {
			return array();
		}

		$list = $fs->dirlist( $dir, false, false );
		if ( ! is_array( $list ) ) {
			return array();
		}

		$backups = array();
		foreach ( $list as $name => $info ) {
			$name = (string) $name;
			if ( preg_match( self::ID_PATTERN, $name ) ) {
				$backups[] = array(
					'id'   => $name,
					'time' => isset( $info['lastmodunix'] ) ? (int) $info['lastmodunix'] : 0,
					'size' => isset( $info['size'] ) ? (int) $info['size'] : 0,
				);
			}
		}

		usort(
			$backups,
			static function ( $a, $b ) {
				return strcmp( $b['id'], $a['id'] );
			}
		);

		return $backups;
	}

	/**
	 * Get the backup folder of one file.
	 *
	 * @param string $path   Absolute path of the original file.
	 * @param bool   $create Whether to create the folder.
	 * @return string Folder path with trailing slash, or an empty string.
	 */
	private function get_file_dir( $path, $create ) {
		$base = $this->get_base_dir( $create );
		if ( '' === $base ) {
			return '';
		}

		$dir = $base . sha1( wp_normalize_path( $path ) ) . '/';

		if ( $create && ! wp_mkdir_p( $dir ) ) {
			return '';
		}

		return $dir;
	}

	/**
	 * Get the base backup folder.
	 *
	 * @param bool $create Whether to create the folder and its guard files.
	 * @return string Folder path with trailing slash, or an empty string.
	 */
	public function get_base_dir( $create ) {
		$uploads = wp_upload_dir( null, false );
		if ( empty( $uploads['basedir'] ) ) {
			return '';
		}

		$folder = get_option( self::OPTION_DIR );
		if ( ! is_string( $folder ) || ! preg_match( '/^filesmith-[a-z0-9]{16}$/', $folder ) ) {
			if ( ! $create ) {
				return '';
			}
			$folder = 'filesmith-' . strtolower( wp_generate_password( 16, false ) );
			update_option( self::OPTION_DIR, $folder, false );
		}

		$base = trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . $folder . '/';

		if ( $create ) {
			if ( ! wp_mkdir_p( $base ) ) {
				return '';
			}
			$this->write_guard_files( $base );
		}

		return $base;
	}

	/**
	 * Add index.php and .htaccess to the backup folder.
	 *
	 * @param string $base Base backup folder.
	 * @return void
	 */
	private function write_guard_files( $base ) {
		$fs = $this->filesystem();
		if ( is_wp_error( $fs ) ) {
			return;
		}

		if ( ! file_exists( $base . 'index.php' ) ) {
			$fs->put_contents( $base . 'index.php', "<?php\n// Silence is golden.\n" );
		}

		if ( ! file_exists( $base . '.htaccess' ) ) {
			$fs->put_contents(
				$base . '.htaccess',
				"# Deny direct access to backups.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n"
			);
		}
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
			return $this->error( __( 'Could not access the filesystem. Direct file access is required.', 'filesmith-editor' ), 500 );
		}

		return $wp_filesystem;
	}

	/**
	 * Build a WP_Error.
	 *
	 * @param string $message Translated message.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error
	 */
	private function error( $message, $status = 500 ) {
		return new \WP_Error( 'fsed_backup_failed', $message, array( 'status' => $status ) );
	}
}
