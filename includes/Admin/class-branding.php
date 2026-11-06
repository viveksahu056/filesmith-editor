<?php
/**
 * Branding: links, About page data and plugin list links.
 *
 * @package FileSmithEditor
 */

namespace FileSmithEditor\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Branding
 *
 * Everything here is static. The only remote request the plugin makes is the
 * update check (see Updater), which can be turned off in wp-config.php.
 */
class Branding {

	/**
	 * Plugin site
	 *
	 * @var string
	 */
	const PLUGIN_URL = 'https://plugins.wpwebguru.com/filesmith-editor/';

	/**
	 * Documentation.
	 *
	 * @var string
	 */
	const PLUGIN_DOC_URL = 'https://plugins.wpwebguru.com/filesmith-editor/docs';

	/**
	 * Author site, also used for the documentation.
	 *
	 * @var string
	 */
	const AUTHOR_URL = 'https://wpwebguru.com/';

	/**
	 * Support forum.
	 *
	 * @var string
	 */
	const SUPPORT_URL = 'https://wpwebguru.com/contact-us/';

	/**
	 * Review page.
	 *
	 * @var string
	 */
	const REVIEW_URL = 'https://plugins.wpwebguru.com/filesmith-editor/';

	/**
	 * Where to buy FileSmith Editor Pro. EDIT: set this to the Freemius checkout or pricing page.
	 *
	 * @var string
	 */
	const UPGRADE_URL = 'https://plugins.wpwebguru.com/filesmith-editor/#pricing';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'plugin_action_links_' . FSED_BASENAME, array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * Add a Settings link in the plugins list.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( add_query_arg( 'page', Admin_Menu::SETTINGS_SLUG, admin_url( 'admin.php' ) ) ),
			esc_html__( 'Settings', 'filesmith-editor' )
		);

		return array_merge( array( $settings ), (array) $links );
	}

	/**
	 * Add Docs, Support and Rate links under the plugin description.
	 *
	 * @param string[] $meta Existing links.
	 * @param string   $file Plugin basename.
	 * @return string[]
	 */
	public function row_meta( $meta, $file ) {
		if ( FSED_BASENAME !== $file ) {
			return $meta;
		}

		$meta[] = '<a href="' . esc_url( self::AUTHOR_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Docs', 'filesmith-editor' ) . '</a>';
		$meta[] = '<a href="' . esc_url( self::SUPPORT_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Support', 'filesmith-editor' ) . '</a>';
		$meta[] = '<a href="' . esc_url( self::REVIEW_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Rate this plugin', 'filesmith-editor' ) . '</a>';

		return $meta;
	}

	/**
	 * Get the other plugins by the author.
	 *
	 * Images are optional. Put a file with the given name in assets/images/ and
	 * it replaces the placeholder.
	 *
	 * @return array[] List of arrays with name, description, url and image.
	 */
	public static function plugins() {
		return array(
			array(
				'name'        => 'SniffPress',
				'description' => __( 'Search through all your theme and plugin files from the WordPress admin.', 'filesmith-editor' ),
				'url'         => 'https://wordpress.org/plugins/sniffpress/',
				'image'       => 'sniffpress.png',
			),
			array(
				'name'        => 'PostMeta Viewer',
				'description' => __( 'Explore, search and analyze post meta and custom fields, including ACF, Pods and Toolset.', 'filesmith-editor' ),
				'url'         => 'https://wordpress.org/plugins/postmeta-viewer/',
				'image'       => 'postmeta-viewer.jpg',
			),
			array(
				'name'        => 'Tastoria Recipe Manager',
				'description' => __( 'Create, organize and share beautiful recipes on your WordPress site.', 'filesmith-editor' ),
				'url'         => 'https://wordpress.org/plugins/tastoria-recipe-manager/',
				'image'       => 'tastoria-recipe-manager.png',
			),
		);
	}

	/**
	 * Get the URL of an image in assets/images/.
	 *
	 * @param string $file File name.
	 * @return string URL, or an empty string when the file does not exist yet.
	 */
	public static function image_url( $file ) {
		$file = basename( (string) $file );

		if ( '' === $file || ! is_file( FSED_PATH . 'assets/images/' . $file ) ) {
			return '';
		}

		return FSED_URL . 'assets/images/' . rawurlencode( $file );
	}
}
