<?php
/**
 * AJAX endpoints.
 *
 * @package FileSmithEditor
 */

namespace FileSmithEditor\Ajax;

use FileSmithEditor\Files\Backup_Manager;
use FileSmithEditor\Files\File_Manager;
use FileSmithEditor\Security\Permissions;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ajax_Handler
 *
 * Every endpoint is POST only, logged-in only, and runs
 * Permissions::authorize() (nonce and capability) before any file work.
 *
 * Common POST fields: nonce, type ('theme' or 'plugin'), slug.
 */
class Ajax_Handler {

	/**
	 * File manager.
	 *
	 * @var File_Manager
	 */
	private $files;

	/**
	 * Backup manager.
	 *
	 * @var Backup_Manager
	 */
	private $backups;

	/**
	 * Constructor.
	 *
	 * @param File_Manager   $files   File manager.
	 * @param Backup_Manager $backups Backup manager.
	 */
	public function __construct( File_Manager $files, Backup_Manager $backups ) {
		$this->files   = $files;
		$this->backups = $backups;
	}

	/**
	 * Register the AJAX actions.
	 *
	 * @return void
	 */
	public function register() {
		$actions = array(
			'tree'        => 'handle_tree',
			'read'        => 'handle_read',
			'save'        => 'handle_save',
			'backups'     => 'handle_backups',
			'backup_read' => 'handle_backup_read',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_fsed_' . $action, array( $this, $method ) );
		}
	}

	/**
	 * Get the file tree. Fields: none.
	 *
	 * @return void
	 */
	public function handle_tree() {
		$req    = $this->start( 'view' );
		$result = $this->files->get_tree( $req['type'], $req['slug'] );

		if ( ! is_wp_error( $result ) ) {
			$result = array( 'tree' => $result );
		}

		$this->respond( $result );
	}

	/**
	 * Read a file. Fields: path.
	 *
	 * @return void
	 */
	public function handle_read() {
		$req = $this->start( 'view' );
		$this->respond( $this->files->read_file( $req['type'], $req['slug'], $this->post_text( 'path' ) ) );
	}

	/**
	 * Save a file. Fields: path, content.
	 *
	 * @return void
	 */
	public function handle_save() {
		$req     = $this->start( 'save' );
		$content = $this->post_code( 'content' );

		if ( null === $content ) {
			$this->respond( new \WP_Error( 'fsed_missing_content', __( 'The server did not receive the file content, so nothing was saved. A firewall or a server limit (max_input_vars or post_max_size) may have removed it.', 'filesmith-editor' ), array( 'status' => 400 ) ) );
		}

		$this->respond( $this->files->save_file( $req['type'], $req['slug'], $this->post_text( 'path' ), $content ) );
	}

	/**
	 * List the backups of a file. Fields: path.
	 *
	 * @return void
	 */
	public function handle_backups() {
		$req = $this->start( 'backups' );
		$this->respond( $this->backups->list_for( $req['type'], $req['slug'], $this->post_text( 'path' ) ) );
	}

	/**
	 * Read one backup. Fields: path, id.
	 *
	 * @return void
	 */
	public function handle_backup_read() {
		$req = $this->start( 'backups' );
		$this->respond( $this->backups->read_for( $req['type'], $req['slug'], $this->post_text( 'path' ), $this->post_text( 'id' ) ) );
	}

	/**
	 * Authorize the request and read the common fields.
	 *
	 * Sends a JSON error and stops when the request is not allowed.
	 *
	 * @param string $action Action key from Settings::actions().
	 * @return array Array with type and slug.
	 */
	private function start( $action ) {
		// A request over post_max_size arrives with an empty $_POST. Say so, instead of showing a misleading security error.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checks that the form data is empty.
		if ( empty( $_POST ) && ! empty( $_SERVER['CONTENT_LENGTH'] ) ) {
			$limit = wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) );

			if ( $limit > 0 && (int) $_SERVER['CONTENT_LENGTH'] > $limit ) {
				$this->respond(
					new \WP_Error(
						'fsed_post_too_large',
						sprintf(
							/* translators: %s: PHP post_max_size value, such as 8M. */
							__( 'The request is larger than the server limit for form data (post_max_size is %s), so nothing was saved. Reduce the file size or ask your host to raise post_max_size.', 'filesmith-editor' ),
							ini_get( 'post_max_size' )
						),
						array( 'status' => 413 )
					)
				);
			}
		}

		$type = $this->post_key( 'type' );

		$auth = Permissions::authorize( $type, $action );
		if ( is_wp_error( $auth ) ) {
			$this->respond( $auth );
		}

		return array(
			'type' => $type,
			'slug' => $this->post_text( 'slug' ),
		);
	}

	/**
	 * Send the JSON response and stop.
	 *
	 * @param array|\WP_Error $result Result of a file operation.
	 * @return void
	 */
	private function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = ( is_array( $data ) && isset( $data['status'] ) ) ? (int) $data['status'] : 400;

			wp_send_json_error(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				$status
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Read a POST value as a key.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	private function post_key( $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in Permissions::authorize().
		return isset( $_POST[ $name ] ) ? sanitize_key( wp_unslash( $_POST[ $name ] ) ) : '';
	}

	/**
	 * Read a POST value as sanitized text.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	private function post_text( $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in Permissions::authorize().
		return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
	}

	/**
	 * Read a POST value as raw code.
	 *
	 * Code must not be sanitized. It is only written to a validated path.
	 *
	 * @param string $name Field name.
	 * @return string|null Null when the field is missing or not a string.
	 */
	private function post_code( $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in Permissions::authorize().
		if ( ! isset( $_POST[ $name ] ) || ! is_string( $_POST[ $name ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Code is written as-is.
		return wp_unslash( $_POST[ $name ] );
	}
}
