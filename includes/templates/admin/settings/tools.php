<?php
/**
 * Template for GatherPress settings tools page.
 *
 * Provides import and export functionality for plugin settings.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

$gatherpress_scope = isset( $scope ) && 'network' === $scope ? 'network' : 'blog';
?>

<div
	class="gatherpress-settings-tools"
	data-gatherpress_component_name="settings-tools"
	data-gatherpress_scope="<?php echo esc_attr( $gatherpress_scope ); ?>"
>
	<h2><?php esc_html_e( 'Export Settings', 'gatherpress' ); ?></h2>
	<p class="description">
		<?php
		if ( 'network' === $gatherpress_scope ) {
			esc_html_e( 'Download the network-wide GatherPress settings (values set on the other tabs at Network Admin) as a JSON file.', 'gatherpress' );
		} else {
			esc_html_e( 'Download your current GatherPress settings as a JSON file. Only non-default values are exported.', 'gatherpress' );
		}
		?>
	</p>
	<p>
		<button type="button" class="button button-primary gatherpress-settings-tools__export">
			<?php esc_html_e( 'Export Settings', 'gatherpress' ); ?>
		</button>
	</p>

	<hr />

	<h2><?php esc_html_e( 'Import Settings', 'gatherpress' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Upload a previously exported JSON file to restore or apply settings.', 'gatherpress' ); ?>
	</p>

	<div class="gatherpress-settings-form">
		<div class="gatherpress-settings-form__row">
			<div class="gatherpress-settings-form__label">
				<label for="gatherpress-import-file"><?php esc_html_e( 'Settings File', 'gatherpress' ); ?></label>
			</div>
			<div class="gatherpress-settings-form__field">
				<input type="file" id="gatherpress-import-file" accept=".json" />
			</div>
		</div>
		<div class="gatherpress-settings-form__row gatherpress-settings-form__row--full">
			<fieldset>
				<legend><?php esc_html_e( 'Import Mode', 'gatherpress' ); ?></legend>
				<label>
					<input type="radio" name="gatherpress_import_mode" value="merge" checked="checked" />
					<?php esc_html_e( 'Merge: import values while keeping existing settings not in the file.', 'gatherpress' ); ?>
				</label>
				<br />
				<label>
					<input type="radio" name="gatherpress_import_mode" value="replace" />
					<?php esc_html_e( 'Replace: overwrite all settings with the file contents.', 'gatherpress' ); ?>
				</label>
			</fieldset>
		</div>
	</div>

	<div class="gatherpress-settings-tools__preview gatherpress--is-hidden">
		<h3><?php esc_html_e( 'Import Preview', 'gatherpress' ); ?></h3>
		<div class="gatherpress-settings-tools__preview-content"></div>
	</div>

	<p>
		<button type="button" class="button button-primary gatherpress-settings-tools__import" disabled>
			<?php esc_html_e( 'Import Settings', 'gatherpress' ); ?>
		</button>
	</p>

	<output class="gatherpress-settings-tools__status">
		<span class="spinner"></span>
		<span class="gatherpress-settings-tools__status-text"></span>
	</output>

	<?php if ( 'network' !== $gatherpress_scope ) : ?>
		<hr />

		<h2><?php esc_html_e( 'Venue Maps', 'gatherpress' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Remove cached static-map images that are no longer referenced by any venue.', 'gatherpress' ); ?>
		</p>
		<p>
			<button type="button" class="button button-primary gatherpress-settings-tools__cleanup-maps">
				<?php esc_html_e( 'Clean up unused venue-map images', 'gatherpress' ); ?>
			</button>
		</p>
		<output class="gatherpress-settings-tools__status gatherpress-settings-tools__cleanup-status">
			<span class="spinner"></span>
			<span class="gatherpress-settings-tools__status-text"></span>
		</output>
	<?php endif; ?>
</div>
