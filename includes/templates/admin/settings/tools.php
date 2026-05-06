<?php
/**
 * Template for GatherPress settings tools page.
 *
 * Provides import and export functionality for plugin settings.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

$gatherpress_nonce = wp_create_nonce( 'gatherpress_tools_nonce' );
$gatherpress_scope = isset( $scope ) && 'network' === $scope ? 'network' : 'blog';
?>

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
	<button id="gatherpress-export" class="button button-primary">
		<?php esc_html_e( 'Export Settings', 'gatherpress' ); ?>
	</button>
</p>

<hr />

<h2><?php esc_html_e( 'Import Settings', 'gatherpress' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Upload a previously exported JSON file to restore or apply settings.', 'gatherpress' ); ?>
</p>

<style>
	.gatherpress-settings-form {
		margin-top: 1em;
	}
	.gatherpress-settings-form__row {
		display: grid;
		grid-template-columns: 200px 1fr;
		gap: 0 10px;
		padding: 15px 10px;
	}
	.gatherpress-settings-form__label {
		font-weight: 600;
		padding-top: 2px;
	}
	.gatherpress-settings-form__row--full > * {
		grid-column: 1 / -1;
	}
	@media screen and (max-width: 782px) {
		.gatherpress-settings-form__row {
			grid-template-columns: 1fr;
			gap: 6px;
		}
	}
</style>

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

<div id="gatherpress-import-preview" style="display: none; background: #f9f9f9; border-left: 4px solid #0073aa; padding: 12px; margin: 16px 0;">
	<h3 style="margin-top: 0;"><?php esc_html_e( 'Import Preview', 'gatherpress' ); ?></h3>
	<div id="gatherpress-import-preview-content"></div>
</div>

<p id="gatherpress-import-saving" class="gatherpress-saving" style="display: none; align-items: center;">
	<span class="spinner is-active" style="float: none;"></span>
	<span style="font-weight: bold;">
		<?php esc_html_e( 'Importing settings...', 'gatherpress' ); ?>
	</span>
</p>

<p>
	<button id="gatherpress-import" class="button button-primary" disabled>
		<?php esc_html_e( 'Import Settings', 'gatherpress' ); ?>
	</button>
</p>

<script>
(function() {
	const nonce = '<?php echo esc_js( $gatherpress_nonce ); ?>';
	const scope = '<?php echo esc_js( $gatherpress_scope ); ?>';
	let importData = null;

	// Export handler.
	document.getElementById('gatherpress-export').addEventListener('click', function(e) {
		e.preventDefault();

		const data = new URLSearchParams({
			action: 'gatherpress_export_settings',
			nonce: nonce,
			scope: scope
		});

		fetch(window.ajaxurl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: data
		})
		.then(function(response) { return response.json(); })
		.then(function(response) {
			if (!response.success) {
				alert('<?php echo esc_js( __( 'Export failed.', 'gatherpress' ) ); ?>');
				return;
			}

			// Download as JSON file.
			const blob = new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' });
			const url = URL.createObjectURL(blob);
			const a = document.createElement('a');
			a.href = url;
			a.download = 'gatherpress-settings-' + new Date().toISOString().slice(0, 10) + '.json';
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
			URL.revokeObjectURL(url);
		});
	});

	// File selection handler — preview.
	document.getElementById('gatherpress-import-file').addEventListener('change', function(e) {
		const file = e.target.files[0];
		const preview = document.getElementById('gatherpress-import-preview');
		const content = document.getElementById('gatherpress-import-preview-content');
		const importBtn = document.getElementById('gatherpress-import');

		if (!file) {
			preview.style.display = 'none';
			importBtn.disabled = true;
			importData = null;
			return;
		}

		const reader = new FileReader();
		reader.onload = function(event) {
			try {
				importData = JSON.parse(event.target.result);
			} catch (err) {
				content.innerHTML = '<p style="color: #d63638;">' +
					'<?php echo esc_js( __( 'Invalid JSON file.', 'gatherpress' ) ); ?>' + '</p>';
				preview.style.display = 'block';
				importBtn.disabled = true;
				importData = null;
				return;
			}

			// Build preview.
			let html = '';

			if (importData.version) {
				html += '<p><strong><?php echo esc_js( __( 'Version:', 'gatherpress' ) ); ?></strong> ' +
					importData.version + '</p>';
			}

			if (importData.exported_at) {
				html += '<p><strong><?php echo esc_js( __( 'Exported:', 'gatherpress' ) ); ?></strong> ' +
					new Date(importData.exported_at).toLocaleString() + '</p>';
			}

			if (importData.settings) {
				const keys = Object.keys(importData.settings);
				html += '<p><strong><?php echo esc_js( __( 'Settings:', 'gatherpress' ) ); ?></strong> ' +
					keys.length + ' ' +
					'<?php echo esc_js( __( 'value(s)', 'gatherpress' ) ); ?></p>';

				if (keys.length > 0) {
					html += '<ul style="margin-left: 20px;">';
					keys.forEach(function(key) {
						html += '<li><code>' + key + '</code></li>';
					});
					html += '</ul>';
				}
			}

			content.innerHTML = html;
			preview.style.display = 'block';
			importBtn.disabled = false;
		};

		reader.readAsText(file);
	});

	// Import handler.
	document.getElementById('gatherpress-import').addEventListener('click', function(e) {
		e.preventDefault();

		if (!importData) {
			return;
		}

		const mode = document.querySelector('input[name="gatherpress_import_mode"]:checked').value;
		const saving = document.getElementById('gatherpress-import-saving');
		const importBtn = document.getElementById('gatherpress-import');

		importBtn.disabled = true;
		saving.style.display = 'flex';

		const data = new URLSearchParams({
			action: 'gatherpress_import_settings',
			nonce: nonce,
			scope: scope,
			settings_json: JSON.stringify(importData),
			import_mode: mode
		});

		fetch(window.ajaxurl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: data
		})
		.then(function(response) { return response.json(); })
		.then(function(response) {
			saving.style.display = 'none';
			importBtn.disabled = false;

			if (response.success) {
				const imported = response.data.imported ? response.data.imported.length : 0;
				const skipped = response.data.skipped ? response.data.skipped.length : 0;
				let msg = imported + ' <?php echo esc_js( __( 'setting(s) imported.', 'gatherpress' ) ); ?>';

				if (skipped > 0) {
					msg += ' ' + skipped + ' <?php echo esc_js( __( 'unknown key(s) skipped.', 'gatherpress' ) ); ?>';
				}

				alert(msg);
			} else {
				const errorMsg = response.data && response.data.message
					? response.data.message
					: '<?php echo esc_js( __( 'Import failed.', 'gatherpress' ) ); ?>';
				alert(errorMsg);
			}
		});
	});
})();
</script>
