<?php
/**
 * Settings page template.
 *
 * @var array $settings      Current settings.
 * @var array $actions       Action => label.
 * @var array $themes        Editor theme => label.
 * @var array $roles         Role slug => name, without administrator.
 *
 * @package FileSmithEditor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$option = \FileSmithEditor\Settings::OPTION;

$fsed_tabs = array(
	'editor' => __( 'Editor', 'filesmith-editor' ),
	'files'  => __( 'Files', 'filesmith-editor' ),
	'access' => __( 'Access control', 'filesmith-editor' ),
	'about'  => __( 'About', 'filesmith-editor' ),
);

$fsed_logo = \FileSmithEditor\Admin\Branding::image_url( 'filesmith-logo.png' );

$fsed_update = ( new \FileSmithEditor\Updater() )->get_status();

$fsed_sample = "<?php\nfunction fsed_hello() {\n\t// FileSmith Editor\n\treturn 'Hello, world';\n}";
?>
<div class="wrap fsed-wrap fsed-settings">
	<h1><?php esc_html_e( 'FileSmith Settings', 'filesmith-editor' ); ?></h1>

	<?php settings_errors(); ?>

	<form method="post" action="options.php" id="fsed-settings-form">
		<?php settings_fields( \FileSmithEditor\Admin\Settings_Page::GROUP ); ?>

		<nav class="nav-tab-wrapper fsed-tab-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'filesmith-editor' ); ?>">
			<?php foreach ( $fsed_tabs as $tab_key => $tab_label ) : ?>
				<a href="#fsed-panel-<?php echo esc_attr( $tab_key ); ?>" class="nav-tab" data-fsed-tab="<?php echo esc_attr( $tab_key ); ?>"><?php echo esc_html( $tab_label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<section id="fsed-panel-editor" class="fsed-panel" data-fsed-panel="editor">
			<div class="fsed-card">
				<h2><?php esc_html_e( 'Editor appearance', 'filesmith-editor' ); ?></h2>
				<p class="fsed-card-intro"><?php esc_html_e( 'How the code editor looks for everyone who uses it.', 'filesmith-editor' ); ?></p>

				<div class="fsed-field">
					<label for="fsed-font-size"><?php esc_html_e( 'Font size (px)', 'filesmith-editor' ); ?></label>
					<input type="number" id="fsed-font-size" class="small-text" name="<?php echo esc_attr( $option ); ?>[font_size]" value="<?php echo esc_attr( $settings['font_size'] ); ?>" min="10" max="24" step="1">
				</div>

				<div class="fsed-field">
					<label for="fsed-editor-theme"><?php esc_html_e( 'Editor theme', 'filesmith-editor' ); ?></label>
					<select id="fsed-editor-theme" name="<?php echo esc_attr( $option ); ?>[editor_theme]">
						<?php foreach ( $themes as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['editor_theme'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="fsed-field">
					<label for="fsed-tab-size"><?php esc_html_e( 'Tab size', 'filesmith-editor' ); ?></label>
					<select id="fsed-tab-size" name="<?php echo esc_attr( $option ); ?>[tab_size]">
						<?php foreach ( array( 2, 4, 8 ) as $fsed_size ) : ?>
							<option value="<?php echo esc_attr( (string) $fsed_size ); ?>" <?php selected( (int) $settings['tab_size'], $fsed_size ); ?>><?php echo esc_html( sprintf( /* translators: %d: number of spaces. */ _n( '%d space', '%d spaces', $fsed_size, 'filesmith-editor' ), $fsed_size ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="fsed-field">
					<input type="hidden" name="<?php echo esc_attr( $option ); ?>[word_wrap]" value="0">
					<label for="fsed-word-wrap" class="fsed-check">
						<input type="checkbox" id="fsed-word-wrap" name="<?php echo esc_attr( $option ); ?>[word_wrap]" value="1" <?php checked( ! empty( $settings['word_wrap'] ) ); ?>>
						<?php esc_html_e( 'Wrap long lines', 'filesmith-editor' ); ?>
					</label>
				</div>

				<div class="fsed-field">
					<input type="hidden" name="<?php echo esc_attr( $option ); ?>[line_numbers]" value="0">
					<label for="fsed-line-numbers" class="fsed-check">
						<input type="checkbox" id="fsed-line-numbers" name="<?php echo esc_attr( $option ); ?>[line_numbers]" value="1" <?php checked( ! empty( $settings['line_numbers'] ) ); ?>>
						<?php esc_html_e( 'Show line numbers', 'filesmith-editor' ); ?>
					</label>
				</div>

				<div class="fsed-field">
					<span class="fsed-label"><?php esc_html_e( 'Preview', 'filesmith-editor' ); ?></span>
					<pre id="fsed-preview" class="fsed-preview"><?php echo esc_html( $fsed_sample ); ?></pre>
				</div>
			</div>
		</section>

		<section id="fsed-panel-files" class="fsed-panel" data-fsed-panel="files">
			<div class="fsed-card">
				<h2><?php esc_html_e( 'Files and backups', 'filesmith-editor' ); ?></h2>
				<p class="fsed-card-intro"><?php esc_html_e( 'Which files can be edited and how many backups are kept.', 'filesmith-editor' ); ?></p>

				<div class="fsed-field">
					<label for="fsed-max-backups"><?php esc_html_e( 'Backups per file', 'filesmith-editor' ); ?></label>
					<input type="number" id="fsed-max-backups" class="small-text" name="<?php echo esc_attr( $option ); ?>[max_backups]" value="<?php echo esc_attr( $settings['max_backups'] ); ?>" min="1" max="5" step="1">
					<p class="description">
						<?php esc_html_e( 'The oldest backups are deleted when this number is passed (1 to 5).', 'filesmith-editor' ); ?>
						<a href="<?php echo esc_url( \FileSmithEditor\Admin\Branding::UPGRADE_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Pro keeps up to 100.', 'filesmith-editor' ); ?></a>
					</p>
				</div>

				<div class="fsed-field">
					<label for="fsed-extensions"><?php esc_html_e( 'Allowed file types', 'filesmith-editor' ); ?></label>
					<textarea id="fsed-extensions" class="large-text code" rows="3" name="<?php echo esc_attr( $option ); ?>[extensions]"><?php echo esc_textarea( implode( ', ', $settings['extensions'] ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'File extensions that can be edited, separated by commas. Example: php, css, js.', 'filesmith-editor' ); ?></p>
				</div>

				<div class="fsed-field">
					<label for="fsed-excluded"><?php esc_html_e( 'Excluded folders', 'filesmith-editor' ); ?></label>
					<textarea id="fsed-excluded" class="large-text code" rows="3" name="<?php echo esc_attr( $option ); ?>[excluded_folders]"><?php echo esc_textarea( implode( "\n", $settings['excluded_folders'] ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Folder names that are hidden from the editor. One per line.', 'filesmith-editor' ); ?></p>
				</div>
			</div>
		</section>

		<section id="fsed-panel-access" class="fsed-panel" data-fsed-panel="access">
			<div class="fsed-card">
				<h2><?php esc_html_e( 'Access by role', 'filesmith-editor' ); ?></h2>
				<?php if ( $blocked_roles ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'Some roles, such as Editor, do not have the WordPress capability edit_themes or edit_plugins, so they cannot use FileSmith and their boxes are locked. Only a role editor plugin or custom code can give a role those capabilities.', 'filesmith-editor' ); ?></p></div>
				<?php endif; ?>
				<p class="fsed-card-intro">
					<?php esc_html_e( 'Administrators always have full access. These settings can only limit access: a user still needs the WordPress capability edit_themes or edit_plugins, and it is not available when DISALLOW_FILE_EDIT is set.', 'filesmith-editor' ); ?>
				</p>

				<div class="fsed-matrix-wrap">
					<table class="widefat striped fsed-matrix">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Role', 'filesmith-editor' ); ?></th>
								<th scope="col"><?php esc_html_e( 'All', 'filesmith-editor' ); ?></th>
								<?php foreach ( $actions as $label ) : ?>
									<th scope="col"><?php echo esc_html( $label ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $roles as $role => $role_name ) : ?>
								<?php
								$role_actions = isset( $settings['roles'][ $role ] ) ? (array) $settings['roles'][ $role ] : array();
								$fsed_blocked = isset( $blocked_roles[ $role ] );
								?>
								<tr>
									<th scope="row">
										<?php echo esc_html( translate_user_role( $role_name ) ); ?>
										<?php if ( $fsed_blocked ) : ?>
											<br><span class="description fsed-blocked-note"><?php esc_html_e( 'Cannot be given access: this role does not have the WordPress capability edit_themes or edit_plugins.', 'filesmith-editor' ); ?></span>
										<?php endif; ?>
									</th>
									<td>
										<input
											type="checkbox"
											data-fsed-row-all
											aria-label="<?php echo esc_attr( sprintf( /* translators: %s: role name. */ __( 'Select all actions for %s', 'filesmith-editor' ), translate_user_role( $role_name ) ) ); ?>" <?php disabled( $fsed_blocked ); ?>
										>
									</td>
									<?php foreach ( $actions as $action => $label ) : ?>
										<td>
											<input
												type="checkbox"
												data-fsed-cell
												name="<?php echo esc_attr( $option ); ?>[roles][<?php echo esc_attr( $role ); ?>][]"
												value="<?php echo esc_attr( $action ); ?>"
												aria-label="<?php echo esc_attr( sprintf( /* translators: 1: role name, 2: action name. */ __( '%1$s: %2$s', 'filesmith-editor' ), translate_user_role( $role_name ), $label ) ); ?>"
												<?php checked( in_array( $action, $role_actions, true ) && ! $fsed_blocked ); ?>
												<?php disabled( $fsed_blocked ); ?>
											>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="fsed-card fsed-pro-card">
				<h2><?php esc_html_e( 'Access by user', 'filesmith-editor' ); ?> <span class="fsed-pro-tag">Pro</span></h2>
				<p class="fsed-card-intro"><?php esc_html_e( 'Give one person their own permissions, and keep an activity log of who changed what. Available in FileSmith Editor Pro.', 'filesmith-editor' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( \FileSmithEditor\Admin\Branding::UPGRADE_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Pro', 'filesmith-editor' ); ?></a></p>
			</div>
		</section>

		<section id="fsed-panel-about" class="fsed-panel" data-fsed-panel="about">
			<div class="fsed-card fsed-about-head">
				<div class="fsed-about-logo">
					<?php if ( $fsed_logo ) : ?>
						<img src="<?php echo esc_url( $fsed_logo ); ?>" alt="" width="96" height="96">
					<?php else : ?>
						<span class="fsed-img-placeholder">filesmith-logo.png<br>128&times;128</span>
					<?php endif; ?>
				</div>
				<div class="fsed-about-text">
					<h2>
						<?php esc_html_e( 'FileSmith Editor', 'filesmith-editor' ); ?>
						<span class="fsed-about-version"><?php echo esc_html( FSED_VERSION ); ?></span>
					</h2>
					<p class="fsed-card-intro"><?php esc_html_e( 'A theme and plugin editor with tabs, quick open, a PHP syntax check and automatic backups. Upgrade to Pro for file management, trash, compare, format and more.', 'filesmith-editor' ); ?></p>
					<p class="fsed-about-links">
						<a class="button" href="<?php echo esc_url( \FileSmithEditor\Admin\Branding::PLUGIN_DOC_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Documentation', 'filesmith-editor' ); ?></a>
						<a class="button" href="<?php echo esc_url( \FileSmithEditor\Admin\Branding::SUPPORT_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get support', 'filesmith-editor' ); ?></a>
						<?php /* ?><a class="button button-primary" href="<?php echo esc_url( \FileSmithEditor\Admin\Branding::REVIEW_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Rate this plugin', 'filesmith-editor' ); ?></a><?php */ ?>
					</p>
				</div>
			</div>

			<div class="fsed-card">
				<h2><?php esc_html_e( 'Updates', 'filesmith-editor' ); ?></h2>
				<?php if ( 'disabled' === $fsed_update['state'] ) : ?>
					<p class="fsed-card-intro"><?php esc_html_e( 'Update checks are turned off in wp-config.php.', 'filesmith-editor' ); ?></p>
				<?php else : ?>
					<p class="fsed-card-intro">
						<?php
						if ( 'available' === $fsed_update['state'] ) {
							echo esc_html(
								sprintf(
									/* translators: 1: new version, 2: installed version. */
									__( 'Version %1$s is available. You have %2$s.', 'filesmith-editor' ),
									$fsed_update['version'],
									FSED_VERSION
								)
							);
						} elseif ( 'current' === $fsed_update['state'] ) {
							echo esc_html(
								sprintf(
									/* translators: %s: installed version. */
									__( 'You have the latest version (%s).', 'filesmith-editor' ),
									FSED_VERSION
								)
							);
						} else {
							esc_html_e( 'The update server could not be reached. Try again later.', 'filesmith-editor' );
						}
						?>
					</p>
					<?php if ( current_user_can( 'update_plugins' ) ) : ?>
						<p>
							<a class="button" href="<?php echo esc_url( $fsed_update['check_url'] ); ?>"><?php esc_html_e( 'Check for updates', 'filesmith-editor' ); ?></a>
							<?php if ( 'available' === $fsed_update['state'] ) : ?>
								<a class="button button-primary" href="<?php echo esc_url( $fsed_update['update_url'] ); ?>"><?php esc_html_e( 'Update now', 'filesmith-editor' ); ?></a>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<div class="fsed-card">
				<h2><?php esc_html_e( 'More plugins by WpWebGuru', 'filesmith-editor' ); ?></h2>
				<p class="fsed-card-intro"><?php esc_html_e( 'Free tools for WordPress developers, from the same author.', 'filesmith-editor' ); ?></p>

				<div class="fsed-plugin-grid">
					<?php foreach ( \FileSmithEditor\Admin\Branding::plugins() as $fsed_plugin ) : ?>
						<?php $fsed_image = \FileSmithEditor\Admin\Branding::image_url( $fsed_plugin['image'] ); ?>
						<div class="fsed-plugin-card">
							<div class="fsed-plugin-icon">
								<?php if ( $fsed_image ) : ?>
									<img src="<?php echo esc_url( $fsed_image ); ?>" alt="" width="64" height="64">
								<?php else : ?>
									<span class="fsed-img-placeholder"><?php echo esc_html( $fsed_plugin['image'] ); ?><br>128&times;128</span>
								<?php endif; ?>
							</div>
							<h3><?php echo esc_html( $fsed_plugin['name'] ); ?></h3>
							<p><?php echo esc_html( $fsed_plugin['description'] ); ?></p>
							<a class="button" href="<?php echo esc_url( $fsed_plugin['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View plugin', 'filesmith-editor' ); ?></a>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<div class="fsed-savebar">
			<?php submit_button( __( 'Save settings', 'filesmith-editor' ), 'primary', 'submit', false ); ?>
		</div>
	</form>
</div>
