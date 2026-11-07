<?php
/**
 * Main plugin class.
 *
 * @package FileSmithEditor
 */

namespace FileSmithEditor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Single instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether the plugin has already been booted.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the single instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Boot the plugin.
	 *
	 * @return void
	 */
	public function run() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Apply the saved settings through the plugin filters.
		Settings::register_hooks();

		if ( wp_doing_ajax() ) {
			$validator = new Files\Path_Validator();
			$backups   = new Files\Backup_Manager( $validator );
			$files     = new Files\File_Manager( $validator );

			// Pre-save filters: syntax check runs first (priority 10), then the backup (priority 20).
			( new Files\Syntax_Checker() )->register();
			$backups->register();

			( new Ajax\Ajax_Handler( $files, $backups ) )->register();
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			$page          = new Admin\Editor_Page();
			$settings_page = new Admin\Settings_Page();
			$settings_page->register();
			( new Admin\Admin_Menu( $page, $settings_page ) )->register();
			( new Admin\Branding() )->register();
			( new Admin\Assets( $page ) )->register();
		}
	}
}
