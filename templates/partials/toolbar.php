<?php
/**
 * Toolbar partial: current file, main buttons and theme/plugin selectors.
 *
 * @var array $types   Allowed types (value => label).
 * @var array $current Selected type and slug.
 * @var array $items   Items of the selected type (slug and name).
 *
 * @package FileSmithEditor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="fsed-toolbar">
	<div class="fsed-toolbar-main">
		<h2 id="fsed-editing" class="fsed-editing"></h2>
		<p class="fsed-file-path">
			<span class="dashicons dashicons-media-code" aria-hidden="true"></span>
			<span class="fsed-file-path-label"><?php esc_html_e( 'File:', 'filesmith-editor' ); ?></span>
			<span id="fsed-current-file">&ndash;</span>
		</p>
	</div>

	<div class="fsed-toolbar-side">
		<div class="fsed-selectors">
			<div class="fsed-select-field">
				<label for="fsed-type"><?php esc_html_e( 'Type', 'filesmith-editor' ); ?></label>
				<select id="fsed-type" title="<?php esc_attr_e( 'Choose whether to edit a theme or a plugin', 'filesmith-editor' ); ?>">
					<?php foreach ( $types as $type => $label ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $current['type'], $type ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="fsed-select-field">
				<label for="fsed-item"><?php esc_html_e( 'Item', 'filesmith-editor' ); ?></label>
				<select id="fsed-item" title="<?php esc_attr_e( 'Choose which theme or plugin to edit', 'filesmith-editor' ); ?>">
					<?php foreach ( $items as $item ) : ?>
						<option value="<?php echo esc_attr( $item['slug'] ); ?>" <?php selected( $current['slug'], $item['slug'] ); ?>><?php echo esc_html( $item['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="fsed-header-actions">
			<button type="button" id="fsed-fullscreen" class="button" aria-pressed="false" title="<?php esc_attr_e( 'Use the whole browser window (Esc to exit)', 'filesmith-editor' ); ?>"><svg class="icon" width="16" height="16" aria-hidden="true" viewBox="0 0 24 24"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" /></svg><span class="fsed-btn-label"><?php esc_html_e( 'Fullscreen', 'filesmith-editor' ); ?></span></button>
			<button type="button" id="fsed-refresh" class="button" title="<?php esc_attr_e( 'Reload the file and the file list from disk (Ctrl+Alt+R)', 'filesmith-editor' ); ?>" disabled><svg class="icon" width="16" height="16" aria-hidden="true" viewBox="0 0 24 24"><path d="M20 12a8 8 0 1 1-2.5-5.8M20 4v5h-5" /></svg><?php esc_html_e( 'Refresh', 'filesmith-editor' ); ?></button>
			<button type="button" id="fsed-backups" class="button" title="<?php esc_attr_e( 'See earlier versions of this file and load one back', 'filesmith-editor' ); ?>" disabled><svg class="icon" width="16" height="16" aria-hidden="true" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5M12 7v5l3 2" /></svg><?php esc_html_e( 'Backups', 'filesmith-editor' ); ?></button>
			<button type="button" id="fsed-save" class="button button-primary" title="<?php esc_attr_e( 'Save the open file (Ctrl+S). A backup is made first.', 'filesmith-editor' ); ?>" disabled><?php esc_html_e( 'Save file', 'filesmith-editor' ); ?></button>
		</div>
	</div>
</div>