<?php
/**
 * Upgrade to Pro page. Static content, no remote requests.
 *
 * @package FileSmithEditor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fsed_upgrade_url = \FileSmithEditor\Admin\Branding::UPGRADE_URL;

$fsed_pro_features = array(
	__( 'Create, upload, rename, move, duplicate and delete files and folders', 'filesmith-editor' ),
	__( 'Right-click menu and download of a theme, plugin, folder or file as a zip', 'filesmith-editor' ),
	__( 'Trash with restore for deleted files', 'filesmith-editor' ),
	__( 'Compare with disk and a line-by-line backup diff', 'filesmith-editor' ),
	__( 'Format for CSS, JS and JSON, plus JSON, JS and CSS checks', 'filesmith-editor' ),
	__( 'Site test after saving PHP, with automatic rollback', 'filesmith-editor' ),
	__( 'Unsaved-draft recovery after a crash or reload', 'filesmith-editor' ),
	__( 'Up to 100 backups per file (Free keeps 5)', 'filesmith-editor' ),
	__( 'Access by user and an activity log', 'filesmith-editor' ),
);

$fsed_plans = array(
	array(
		'name'    => __( '1 site', 'filesmith-editor' ),
		'price'   => '$39',
		'renewal' => '$27',
	),
	array(
		'name'    => __( '5 sites', 'filesmith-editor' ),
		'price'   => '$79',
		'renewal' => '$55',
		'popular' => true,
	),
	array(
		'name'    => __( 'Unlimited sites', 'filesmith-editor' ),
		'price'   => '$129',
		'renewal' => '$90',
	),
);
?>
<div class="wrap fsed-wrap fsed-upgrade">
	<h1><?php esc_html_e( 'Upgrade to FileSmith Pro', 'filesmith-editor' ); ?></h1>
	<p class="fsed-upgrade-lede"><?php esc_html_e( 'You are using the free version. Pro adds file management and the safety and code tools, in the same familiar editor.', 'filesmith-editor' ); ?></p>

	<div class="fsed-upgrade-grid">
		<div class="fsed-upgrade-card">
			<h2><?php esc_html_e( 'What Pro adds', 'filesmith-editor' ); ?></h2>
			<ul class="fsed-upgrade-list">
				<?php foreach ( $fsed_pro_features as $fsed_feature ) : ?>
					<li><?php echo esc_html( $fsed_feature ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="fsed-upgrade-card">
			<h2><?php esc_html_e( 'Plans', 'filesmith-editor' ); ?></h2>
			<ul class="fsed-upgrade-plans">
				<?php foreach ( $fsed_plans as $fsed_plan ) : ?>
					<li<?php echo ! empty( $fsed_plan['popular'] ) ? ' class="is-popular"' : ''; ?>>
						<span class="fsed-plan-name"><?php echo esc_html( $fsed_plan['name'] ); ?></span>
						<span class="fsed-plan-price">
							<strong><?php echo esc_html( $fsed_plan['price'] ); ?></strong>
							<small>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: renewal price per year. */
										__( 'first year, then %s/yr', 'filesmith-editor' ),
										$fsed_plan['renewal']
									)
								);
								?>
							</small>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="fsed-upgrade-note"><?php esc_html_e( 'Each plan includes 1 year of updates and support. Pro keeps working if you do not renew. 30-day money-back guarantee.', 'filesmith-editor' ); ?></p>
			<p>
				<a class="button button-primary button-hero" href="<?php echo esc_url( $fsed_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'See plans and get Pro', 'filesmith-editor' ); ?></a>
			</p>
		</div>
	</div>

	<div class="fsed-upgrade-card fsed-upgrade-install">
		<h2><?php esc_html_e( 'How to install Pro', 'filesmith-editor' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Buy a plan and download the Pro zip.', 'filesmith-editor' ); ?></li>
			<li><?php esc_html_e( 'Go to Plugins, Add New Plugin, Upload Plugin, choose the Pro zip and click Install Now.', 'filesmith-editor' ); ?></li>
			<li><?php esc_html_e( 'Click Activate. The free version steps aside, and your settings and backups are kept.', 'filesmith-editor' ); ?></li>
		</ol>
	</div>
</div>
