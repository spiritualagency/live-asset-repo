<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

$plugins = permanent_plugin_theme_downloader_get_plugins();
$themes = permanent_plugin_theme_downloader_get_themes();
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
	<div class="pptd-header">
		<h3><?php esc_html_e( 'Available Downloads', 'permanent-plugin-theme-downloader' ); ?></h3>
	</div>

	<?php if ( ! empty( $plugins ) ) : ?>
		<div class="pptd-section">
			<h4 class="pptd-section-title">
				<?php esc_html_e( 'Plugins', 'permanent-plugin-theme-downloader' ); ?> (<?php echo count( $plugins ); ?>)
			</h4>
			<div class="pptd-frontend-list">
				<?php foreach ( $plugins as $plugin ) : ?>
					<a href="<?php echo esc_url( $plugin['url'] ); ?>" class="pptd-download-link" download>
						<?php echo esc_html( $plugin['name'] ); ?>
						<span class="pptd-item-version">v<?php echo esc_html( $plugin['version'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $themes ) ) : ?>
		<div class="pptd-section">
			<h4 class="pptd-section-title">
				<?php esc_html_e( 'Themes', 'permanent-plugin-theme-downloader' ); ?> (<?php echo count( $themes ); ?>)
			</h4>
			<div class="pptd-frontend-list">
				<?php foreach ( $themes as $theme ) : ?>
					<a href="<?php echo esc_url( $theme['url'] ); ?>" class="pptd-download-link" download>
						<?php echo esc_html( $theme['name'] ); ?>
						<span class="pptd-item-version">v<?php echo esc_html( $theme['version'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( empty( $plugins ) && empty( $themes ) ) : ?>
		<p class="pptd-empty">
			<?php esc_html_e( 'No plugins or themes available for download.', 'permanent-plugin-theme-downloader' ); ?>
		</p>
	<?php endif; ?>
</div>