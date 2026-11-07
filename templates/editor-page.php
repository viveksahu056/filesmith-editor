<?php
/**
 * Editor page template.
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
<div class="wrap fsed-wrap fsed-theme-<?php echo esc_attr( (string) \FileSmithEditor\Settings::get( 'editor_theme' ) ); ?>">
	<h1><?php esc_html_e( 'FileSmith Editor', 'filesmith-editor' ); ?></h1>

	<div id="fsed-notice" class="notice fsed-notice" hidden>
		<p id="fsed-notice-text"></p>
	</div>

	<div class="fsed-wrapper">
		<?php require FSED_PATH . 'templates/partials/toolbar.php'; ?>

		<div class="fsed-layout">
			<div class="fsed-editor-pane">
				<div id="fsed-filetabs" class="fsed-filetabs" role="tablist" aria-label="<?php esc_attr_e( 'Open files', 'filesmith-editor' ); ?>" hidden></div>

				<div class="fsed-editor-box">
					<label for="fsed-editor" class="screen-reader-text"><?php esc_html_e( 'File content', 'filesmith-editor' ); ?></label>
					<textarea
						id="fsed-editor"
						class="fsed-editor"
						readonly
						wrap="off"
						spellcheck="false"
					></textarea>

					<div id="fsed-image-preview" class="fsed-image-preview" hidden>
						<div class="fsed-image-stage"><img id="fsed-image" alt=""></div>
						<p id="fsed-image-info" class="fsed-image-info"></p>
					</div>

					<div id="fsed-editor-status" class="fsed-editor-status is-loading" role="status" aria-live="polite">
						<span class="spinner is-active"></span>
						<span id="fsed-editor-status-text"><?php esc_html_e( 'Loading files…', 'filesmith-editor' ); ?></span>
					</div>
				</div>

				<div class="fsed-statusbar">
					<span id="fsed-sb-type" class="fsed-sb-item"></span>
					<span id="fsed-sb-pos" class="fsed-sb-item"></span>
					<span class="fsed-sb-spacer"></span>
					<span id="fsed-sb-state" class="fsed-badge"></span>
					<span id="fsed-sb-access" class="fsed-badge"></span>
				</div>
			</div>

			<?php require FSED_PATH . 'templates/partials/file-tree.php'; ?>
		</div>
	</div>

	<p class="fsed-footer">
		<?php
		echo wp_kses(
			sprintf(
				/* translators: 1: plugin name, 2: version, 3: author link. */
				__( '%1$s %2$s by %3$s', 'filesmith-editor' ),
				'FileSmith Editor',
				esc_html( FSED_VERSION ),
				'<a href="' . esc_url( \FileSmithEditor\Admin\Branding::AUTHOR_URL ) . '" target="_blank" rel="noopener noreferrer">WpWebGuru</a>'
			),
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			)
		);
		?>
		<span aria-hidden="true">&middot;</span>
		<a href="<?php echo esc_url( \FileSmithEditor\Admin\Branding::SUPPORT_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support', 'filesmith-editor' ); ?></a>
		<span aria-hidden="true">&middot;</span>
		<a href="<?php echo esc_url( \FileSmithEditor\Admin\Branding::REVIEW_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Rate this plugin', 'filesmith-editor' ); ?></a>
		<span aria-hidden="true">&middot;</span>
		<a href="<?php echo esc_url( add_query_arg( 'page', \FileSmithEditor\Admin\Admin_Menu::UPGRADE_SLUG, admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Upgrade to Pro', 'filesmith-editor' ); ?></a>
		<span aria-hidden="true">&middot;</span>
		<a href="<?php echo esc_url( add_query_arg( 'page', \FileSmithEditor\Admin\Admin_Menu::SETTINGS_SLUG, admin_url( 'admin.php' ) ) . '#fsed-panel-about' ); ?>"><?php esc_html_e( 'More plugins', 'filesmith-editor' ); ?></a>
	</p>

	<?php require FSED_PATH . 'templates/partials/modals.php'; ?>
</div>
