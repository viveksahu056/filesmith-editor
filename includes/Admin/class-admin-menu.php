<?php
/**
 * Admin menu.
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
 * Class Admin_Menu
 */
class Admin_Menu {

	/**
	 * Menu page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'filesmith-editor';

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const SETTINGS_SLUG = 'filesmith-settings';

	/**
	 * Upgrade page slug.
	 *
	 * @var string
	 */
	const UPGRADE_SLUG = 'filesmith-upgrade';

	/**
	 * Editor page.
	 *
	 * @var Editor_Page
	 */
	private $page;

	/**
	 * Settings page.
	 *
	 * @var Settings_Page
	 */
	private $settings_page;

	/**
	 * Constructor.
	 *
	 * @param Editor_Page   $page          Editor page.
	 * @param Settings_Page $settings_page Settings page.
	 */
	public function __construct( Editor_Page $page, Settings_Page $settings_page ) {
		$this->page          = $page;
		$this->settings_page = $settings_page;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Add the top level menu and its pages.
	 *
	 * @return void
	 */
	public function add_menu() {
		$capability = current_user_can( 'edit_themes' ) ? 'edit_themes' : 'edit_plugins';

		add_menu_page(
			__( 'FileSmith Editor', 'filesmith-editor' ),
			__( 'FileSmith', 'filesmith-editor' ),
			$capability,
			self::PAGE_SLUG,
			array( $this->page, 'render' ),
			'dashicons-editor-code',
			80
		);

		// Same slug as the top level item, so the first submenu is named Editor instead of FileSmith.
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'FileSmith Editor', 'filesmith-editor' ),
			__( 'Editor', 'filesmith-editor' ),
			$capability,
			self::PAGE_SLUG,
			array( $this->page, 'render' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'FileSmith Settings', 'filesmith-editor' ),
			__( 'Settings', 'filesmith-editor' ),
			Settings::capability(),
			self::SETTINGS_SLUG,
			array( $this->settings_page, 'render' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Upgrade to FileSmith Pro', 'filesmith-editor' ),
			'<span class="fsed-menu-upgrade">' . esc_html__( 'Upgrade to Pro', 'filesmith-editor' ) . '</span>',
			$capability,
			self::UPGRADE_SLUG,
			array( $this, 'render_upgrade' )
		);
	}

	/**
	 * Render the upgrade page.
	 *
	 * @return void
	 */
	public function render_upgrade() {
		include FSED_PATH . 'templates/upgrade-page.php';
	}
}
