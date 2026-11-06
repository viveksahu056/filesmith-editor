<?php
/**
 * File tree partial. The tree is filled by assets/js/file-tree.js.
 *
 * @package FileSmithEditor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="fsed-sidebar">
	<p class="fsed-pro-hint">
		<a href="<?php echo esc_url( \FileSmithEditor\Admin\Branding::UPGRADE_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Pro: create, upload, rename, move and delete files', 'filesmith-editor' ); ?></a>
	</p>

	<p class="fsed-selected">
		<?php esc_html_e( 'Selected:', 'filesmith-editor' ); ?>
		<strong id="fsed-selected-path">&ndash;</strong>
	</p>

	<div id="fsed-tree" class="fsed-tree" aria-live="polite"></div>
</div>
