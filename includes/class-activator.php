<?php
/**
 * Plugin activation.
 *
 * @package FileSmithEditor
 */

namespace FileSmithEditor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 */
class Activator {

	/**
	 * Run on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wp_version;

		if ( version_compare( PHP_VERSION, FSED_MIN_PHP, '<' ) || version_compare( $wp_version, FSED_MIN_WP, '<' ) ) {
			deactivate_plugins( FSED_BASENAME );
			wp_die(
				sprintf(
					/* translators: 1: required PHP version, 2: required WordPress version. */
					esc_html__( 'FileSmith Editor requires PHP %1$s and WordPress %2$s or higher.', 'filesmith-editor' ),
					esc_html( FSED_MIN_PHP ),
					esc_html( FSED_MIN_WP )
				),
				esc_html__( 'Plugin activation error', 'filesmith-editor' ),
				array( 'back_link' => true )
			);
		}

		update_option( 'fsed_version', FSED_VERSION );
	}
}
