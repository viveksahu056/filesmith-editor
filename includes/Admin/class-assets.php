<?php
/**
 * Admin scripts and styles.
 *
 * @package FileSmithEditor
 */

namespace FileSmithEditor\Admin;

use FileSmithEditor\Security\Permissions;
use FileSmithEditor\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 */
class Assets {

	/**
	 * Editor page.
	 *
	 * @var Editor_Page
	 */
	private $page;

	/**
	 * Constructor.
	 *
	 * @param Editor_Page $page Editor page.
	 */
	public function __construct( Editor_Page $page ) {
		$this->page = $page;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Check whether a hook suffix belongs to one of the plugin pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @param string $slug        Page slug.
	 * @return bool
	 */
	private function is_page( $hook_suffix, $slug ) {
		$ending = '_page_' . $slug;

		return substr( (string) $hook_suffix, -strlen( $ending ) ) === $ending;
	}

	/**
	 * Enqueue assets on the plugin pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		// The hook suffix prefix depends on the menu title, so match the slug at the end.
		if ( $this->is_page( $hook_suffix, Admin_Menu::SETTINGS_SLUG ) ) {
			wp_enqueue_style( 'fsed-admin', FSED_URL . 'assets/css/admin.css', array(), FSED_VERSION );
			wp_enqueue_script( 'fsed-settings', FSED_URL . 'assets/js/settings.js', array(), FSED_VERSION, true );

			return;
		}

		if ( ! $this->is_page( $hook_suffix, Admin_Menu::PAGE_SLUG ) ) {
			return;
		}

		// Returns false when the user turned off syntax highlighting in their profile.
		$editor_settings = wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );

		if ( is_array( $editor_settings ) ) {
			$tab_size = (int) Settings::get( 'tab_size' );

			$editor_settings['codemirror'] = array_merge(
				isset( $editor_settings['codemirror'] ) ? (array) $editor_settings['codemirror'] : array(),
				array(
					'lineWrapping' => (bool) Settings::get( 'word_wrap' ),
					'lineNumbers'  => (bool) Settings::get( 'line_numbers' ),
					'tabSize'      => $tab_size,
					'indentUnit'   => $tab_size,
					'gutters'      => (bool) Settings::get( 'line_numbers' ) ? array( 'CodeMirror-linenumbers' ) : array(),
					'lint'         => false,
				)
			);
		}

		$deps = array( 'fsed-file-tree' );
		if ( false !== $editor_settings ) {
			$deps[] = 'code-editor';
		}

		wp_enqueue_style( 'fsed-admin', FSED_URL . 'assets/css/admin.css', array(), FSED_VERSION );

		wp_add_inline_style(
			'fsed-admin',
			sprintf(
				'.fsed-editor-pane .CodeMirror, .fsed-editor { font-size: %dpx; }',
				(int) Settings::get( 'font_size' )
			)
		);

		$can = array();
		foreach ( array_keys( Settings::actions() ) as $action ) {
			$can[ $action ] = Permissions::can_do( $action );
		}

		wp_enqueue_script( 'fsed-file-tree', FSED_URL . 'assets/js/file-tree.js', array(), FSED_VERSION, true );
		wp_enqueue_script( 'fsed-admin', FSED_URL . 'assets/js/admin.js', $deps, FSED_VERSION, true );

		wp_localize_script(
			'fsed-admin',
			'fsedData',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => Permissions::create_nonce(),
				'userId'         => get_current_user_id(),
				'can'            => $can,
				'items'          => $this->page->get_all_items(),
				'current'        => $this->page->get_current(),
				'editorSettings' => false !== $editor_settings ? $editor_settings : null,
				'i18n'           => array(
					'loading'           => __( 'Loading files…', 'filesmith-editor' ),
					'empty'             => __( 'No files found.', 'filesmith-editor' ),
					'error'             => __( 'Something went wrong. Please try again.', 'filesmith-editor' ),
					'save'              => __( 'Save file', 'filesmith-editor' ),
					'saving'            => __( 'Saving…', 'filesmith-editor' ),
					'saved'             => __( 'File saved.', 'filesmith-editor' ),
					'notWritable'       => __( 'This file is not writable, so it is open in read-only mode.', 'filesmith-editor' ),
					'confirmDiscard'    => __( 'You have unsaved changes. Discard them?', 'filesmith-editor' ),
					'refreshed'         => __( 'Refreshed from disk.', 'filesmith-editor' ),
					'quickOpen'         => __( 'Quick open', 'filesmith-editor' ),
					'quickOpenHint'     => __( 'Type part of a file name…', 'filesmith-editor' ),
					'quickOpenNone'     => __( 'No matching files.', 'filesmith-editor' ),
					'errNetwork'        => __( 'Could not reach the server, so nothing was saved. Check your internet connection and try again.', 'filesmith-editor' ),
					'errSession'        => __( 'Your login session or the page security token has expired, so nothing was saved. Copy your changes, reload the page and log in again.', 'filesmith-editor' ),
					'errBlocked'        => __( 'The server blocked the request (HTTP %d). A firewall, ModSecurity or a security plugin may be treating the code as an attack. The file was not saved. Ask your host to allow it, or check the security plugin log.', 'filesmith-editor' ),
					'errTooBig'         => __( 'The request is too large for the server (HTTP 413), so the file was not saved. Reduce the file size or ask your host to raise the limit.', 'filesmith-editor' ),
					'errServer'         => __( 'The server had an error while handling the request (HTTP %d), so the file may not have been saved. Check the PHP error log on the server.', 'filesmith-editor' ),
					'errUnavailable'    => __( 'The server timed out or is unavailable (HTTP %d), so the file may not have been saved. Try again in a moment.', 'filesmith-editor' ),
					'errUnexpected'     => __( 'The server sent an unexpected response (HTTP %d), so the file may not have been saved.', 'filesmith-editor' ),
					'tipFullscreen'     => __( 'Use the whole browser window (Esc to exit)', 'filesmith-editor' ),
					'tipExitFullscreen' => __( 'Go back to the normal view (Esc)', 'filesmith-editor' ),
					'fullscreen'        => __( 'Fullscreen', 'filesmith-editor' ),
					'exitFullscreen'    => __( 'Exit fullscreen', 'filesmith-editor' ),
					'confirmReload'     => __( 'You have unsaved changes in this file. Discard them and reload the file from disk?', 'filesmith-editor' ),
					'confirmOverwrite'  => __( 'This file was changed on disk after you opened it. Saving will overwrite those changes. Save anyway? (Use Refresh to see the disk version first.)', 'filesmith-editor' ),
					'loadingBackups'    => __( 'Loading backups…', 'filesmith-editor' ),
					'noBackups'         => __( 'No backups yet. A backup is created each time you save a changed file.', 'filesmith-editor' ),
					'selectBackup'      => __( 'Select a backup to preview it.', 'filesmith-editor' ),
					'backupLoaded'      => __( 'Backup loaded into the editor. Review it, then click Save to apply it.', 'filesmith-editor' ),
					/* translators: %s: theme or plugin name. */
					'titleActive'       => __( 'Editing %s (active)', 'filesmith-editor' ),
					/* translators: %s: theme or plugin name. */
					'titleInactive'     => __( 'Editing %s (inactive)', 'filesmith-editor' ),
					/* translators: %s: name of the user who saved the change. */
					'backupBy'          => __( 'Saved by %s', 'filesmith-editor' ),
					'backupUnknown'     => __( 'Saved by an unknown user', 'filesmith-editor' ),
					'loadingFile'       => __( 'Loading file…', 'filesmith-editor' ),
					/* translators: 1: line number, 2: column number. */
					'lineCol'           => __( 'Ln %1$d, Col %2$d', 'filesmith-editor' ),
					'unsaved'           => __( 'Unsaved changes', 'filesmith-editor' ),
					'noChanges'         => __( 'No changes', 'filesmith-editor' ),
					'editable'          => __( 'Editable', 'filesmith-editor' ),
					'readOnly'          => __( 'Read-only', 'filesmith-editor' ),
					'noEditAccess'      => __( 'You can view this file, but you do not have permission to edit it.', 'filesmith-editor' ),
					'selectFile'        => __( 'Select a file from the list to view it.', 'filesmith-editor' ),
					/* translators: %s: file name. */
					'closeTab'          => __( 'Close %s', 'filesmith-editor' ),
					'confirmClose'      => __( 'This file has unsaved changes. Close it and discard them?', 'filesmith-editor' ),
					'tooManyTabs'       => __( 'Too many files are open. Close a tab first.', 'filesmith-editor' ),
				),
			)
		);
	}
}
