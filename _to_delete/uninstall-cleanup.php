<?php
/**
 * Uninstall cleanup.
 *
 * Runs through the Freemius "after_uninstall" action. Removes the plugin
 * options, the activity log table and the backup folder. Define FSED_KEEP_DATA
 * as true in wp-config.php to keep them.
 *
 * @package FileSmithEditor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'fsed_uninstall_site' ) ) {
	/**
	 * Remove the data of the current site.
	 *
	 * @return void
	 */
	function fsed_uninstall_site() {
		$folder = get_option( 'fsed_backup_dir' );

		// Only a folder with the exact name the plugin generates is ever deleted.
		if ( is_string( $folder ) && preg_match( '/^filesmith-[a-z0-9]{16}$/', $folder ) ) {
			$uploads = wp_upload_dir( null, false );

			if ( ! empty( $uploads['basedir'] ) ) {
				$dir = trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . $folder;

				if ( is_dir( $dir ) && ! is_link( $dir ) ) {
					global $wp_filesystem;

					if ( ! function_exists( 'WP_Filesystem' ) ) {
						require_once ABSPATH . 'wp-admin/includes/file.php';
					}

					WP_Filesystem();

					if ( is_object( $wp_filesystem ) ) {
						$wp_filesystem->delete( $dir, true );
					}
				}
			}
		}

		delete_option( 'fsed_backup_dir' );
		delete_option( 'fsed_settings' );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Removes the plugin table on uninstall.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}fsed_log" );
		delete_option( 'fsed_db_version' );
		delete_transient( 'fsed_log_pruned' );
		delete_option( 'fsed_version' );
	}
}

if ( ! function_exists( 'fsed_uninstall_cleanup' ) ) {
	/**
	 * Remove the data of every site that has it.
	 *
	 * @return void
	 */
	function fsed_uninstall_cleanup() {
		if ( defined( 'FSED_KEEP_DATA' ) && FSED_KEEP_DATA ) {
			return;
		}

		if ( is_multisite() ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );
				fsed_uninstall_site();
				restore_current_blog();
			}
		} else {
			fsed_uninstall_site();
		}
	}
}
