<?php
/**
 * Uninstall routine.
 *
 * Removes the plugin options and the backup folder. Define FSED_KEEP_DATA as
 * true in wp-config.php to keep them.
 *
 * @package FileSmithEditor
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( defined( 'FSED_KEEP_DATA' ) && FSED_KEEP_DATA ) {
	return;
}

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
	delete_site_transient( 'fsed_update_manifest' );
}

if ( is_multisite() ) {
	$fsed_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $fsed_site_ids as $fsed_site_id ) {
		switch_to_blog( $fsed_site_id );
		fsed_uninstall_site();
		restore_current_blog();
	}
} else {
	fsed_uninstall_site();
}
