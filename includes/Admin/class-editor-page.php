<?php
/**
 * Editor admin page.
 *
 * @package FileSmithEditor
 */

namespace FileSmithEditor\Admin;

use FileSmithEditor\Security\Permissions;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Editor_Page
 */
class Editor_Page {

	/**
	 * Get the types the current user can manage.
	 *
	 * @return array Type => label.
	 */
	public function get_types() {
		$types = array(
			'theme'  => __( 'Theme', 'filesmith-editor' ),
			'plugin' => __( 'Plugin', 'filesmith-editor' ),
		);

		foreach ( array_keys( $types ) as $type ) {
			if ( ! Permissions::can_manage( $type ) ) {
				unset( $types[ $type ] );
			}
		}

		return $types;
	}

	/**
	 * Get the themes or plugins of a type.
	 *
	 * Single-file plugins are left out because they are not supported.
	 *
	 * @param string $type Either 'theme' or 'plugin'.
	 * @return array[] List of arrays with slug, name and active.
	 */
	public function get_items( $type ) {
		$items = array();

		if ( 'theme' === $type ) {
			foreach ( wp_get_themes() as $stylesheet => $theme ) {
				$items[] = array(
					'slug'   => (string) $stylesheet,
					'name'   => (string) $theme->get( 'Name' ),
					'active' => get_stylesheet() === (string) $stylesheet,
				);
			}
		} elseif ( 'plugin' === $type ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			foreach ( get_plugins() as $file => $data ) {
				if ( '.' === dirname( $file ) ) {
					continue;
				}
				$items[] = array(
					'slug'   => (string) $file,
					'name'   => '' !== $data['Name'] ? (string) $data['Name'] : (string) $file,
					'active' => is_plugin_active( $file ) || is_plugin_active_for_network( $file ),
				);
			}
		}

		usort(
			$items,
			static function ( $a, $b ) {
				return strnatcasecmp( $a['name'], $b['name'] );
			}
		);

		return $items;
	}

	/**
	 * Get the items of every allowed type.
	 *
	 * @return array Type => list of items.
	 */
	public function get_all_items() {
		$all = array();
		foreach ( array_keys( $this->get_types() ) as $type ) {
			$all[ $type ] = $this->get_items( $type );
		}
		return $all;
	}

	/**
	 * Get the selected type and item.
	 *
	 * @return array Array with type and slug.
	 */
	public function get_current() {
		$types = $this->get_types();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selection.
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selection.
		$slug = isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : '';

		if ( ! isset( $types[ $type ] ) ) {
			$type = (string) array_key_first( $types );
		}

		$slugs = wp_list_pluck( $this->get_items( $type ), 'slug' );

		if ( ! in_array( $slug, $slugs, true ) ) {
			$slug = 'theme' === $type ? get_stylesheet() : '';
			if ( ! in_array( $slug, $slugs, true ) ) {
				$slug = $slugs ? $slugs[0] : '';
			}
		}

		return array(
			'type' => $type,
			'slug' => $slug,
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render() {
		$types = $this->get_types();

		if ( ! $types ) {
			wp_die(
				esc_html__( 'You are not allowed to access this page.', 'filesmith-editor' ),
				'',
				array( 'response' => 403 )
			);
		}

		$current = $this->get_current();
		$items   = $this->get_items( $current['type'] );

		include FSED_PATH . 'templates/editor-page.php';
	}
}
