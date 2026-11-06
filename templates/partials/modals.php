<?php
/**
 * Modals partial. Filled and opened by assets/js/admin.js.
 *
 * @package FileSmithEditor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="fsed-modal" class="fsed-modal" role="dialog" aria-modal="true" aria-labelledby="fsed-modal-title" hidden>
	<div class="fsed-modal-backdrop" data-fsed-close></div>

	<div class="fsed-modal-dialog">
		<div class="fsed-modal-header">
			<h2 id="fsed-modal-title"><?php esc_html_e( 'Backups', 'filesmith-editor' ); ?></h2>
			<button type="button" class="fsed-modal-close" data-fsed-close aria-label="<?php esc_attr_e( 'Close', 'filesmith-editor' ); ?>">&times;</button>
		</div>

		<p id="fsed-modal-file" class="fsed-modal-file"></p>
		<p id="fsed-modal-status" class="fsed-modal-status" aria-live="polite"></p>

		<div class="fsed-modal-body">
			<ul id="fsed-backup-list" class="fsed-backup-list"></ul>

			<div class="fsed-backup-preview">
				<label for="fsed-backup-preview" class="screen-reader-text"><?php esc_html_e( 'Backup preview', 'filesmith-editor' ); ?></label>
				<textarea id="fsed-backup-preview" readonly wrap="off" spellcheck="false"></textarea>
			</div>
		</div>

		<div class="fsed-modal-footer">
			<a class="fsed-pro-hint" href="<?php echo esc_url( \FileSmithEditor\Admin\Branding::UPGRADE_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Pro: line-by-line changes view and 100 backups', 'filesmith-editor' ); ?></a>
			<button type="button" class="button" data-fsed-close><?php esc_html_e( 'Close', 'filesmith-editor' ); ?></button>
			<button type="button" id="fsed-restore" class="button button-primary" title="<?php esc_attr_e( 'Put the selected backup into the editor. Nothing is saved until you click Save file.', 'filesmith-editor' ); ?>" disabled><?php esc_html_e( 'Load into editor', 'filesmith-editor' ); ?></button>
		</div>
	</div>
</div>
