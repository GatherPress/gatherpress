/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { REST_NAMESPACE } from './namespace';

const HIDDEN_CLASS = 'gatherpress--is-hidden';
const SETTINGS_PATH = `/${ REST_NAMESPACE }/settings`;

// Each file read takes a number, so a slow read can't replace a newer one.
let latestPreview = 0;

/**
 * Show a status line under the Import Settings button.
 *
 * The status line stays in the page while empty, so a screen reader is
 * already watching it when the first message arrives.
 *
 * @since TBD
 *
 * @param {HTMLElement} status The status output element.
 * @param {string}      text   The text to announce.
 * @param {boolean}     busy   Whether to show the spinner.
 *
 * @return {void}
 */
function showStatus( status, text, busy ) {
	status.querySelector( '.spinner' )?.classList.toggle( 'is-active', busy );
	const textElement = status.querySelector(
		'.gatherpress-settings-tools__status-text, .gatherpress-settings-tools__cleanup-status-text',
	);

	if ( textElement ) {
		textElement.textContent = text;
	}
}

/**
 * Add a paragraph with a bold label and a value to the import preview.
 *
 * @since TBD
 *
 * @param {HTMLElement} content The preview content element.
 * @param {string}      label   The label.
 * @param {string}      value   The value, set as text so it is never parsed as HTML.
 *
 * @return {void}
 */
function addPreviewLine( content, label, value ) {
	const line = document.createElement( 'p' );
	const strong = document.createElement( 'strong' );

	strong.textContent = label;
	line.append( strong, ` ${ value }` );
	content.append( line );
}

/**
 * Show what an import file holds before it is imported.
 *
 * @since TBD
 *
 * @param {HTMLElement} content The preview content element.
 * @param {Object}      data    The parsed import file.
 *
 * @return {void}
 */
export function renderPreview( content, data ) {
	content.replaceChildren();

	if ( data.version ) {
		addPreviewLine( content, __( 'Version:', 'gatherpress' ), data.version );
	}

	if ( data.exported_at ) {
		addPreviewLine(
			content,
			__( 'Exported:', 'gatherpress' ),
			new Date( data.exported_at ).toLocaleString(),
		);
	}

	if ( data.settings ) {
		const keys = Object.keys( data.settings );

		addPreviewLine(
			content,
			__( 'Settings:', 'gatherpress' ),
			sprintf(
				/* translators: %d: Number of settings in the import file. */
				_n( '%d value', '%d values', keys.length, 'gatherpress' ),
				keys.length,
			),
		);

		if ( keys.length ) {
			const list = document.createElement( 'ul' );

			keys.forEach( ( key ) => {
				const item = document.createElement( 'li' );
				const code = document.createElement( 'code' );

				code.textContent = key;
				item.append( code );
				list.append( item );
			} );

			content.append( list );
		}
	}
}

/**
 * Describe the result of an import.
 *
 * @since TBD
 *
 * @param {Object}   result                  The import result from the REST route.
 * @param {string[]} [result.imported]       Keys that were imported.
 * @param {string[]} [result.skipped]        Unknown keys that were skipped.
 * @param {string[]} [result.not_importable] Keys that can't be imported.
 *
 * @return {string} The message to show.
 */
export function describeImport( result ) {
	const imported = result.imported?.length ?? 0;
	const skipped = result.skipped?.length ?? 0;
	const blocked = result.not_importable?.length ?? 0;
	const parts = [
		sprintf(
			/* translators: %d: Number of settings imported. */
			_n( '%d setting imported.', '%d settings imported.', imported, 'gatherpress' ),
			imported,
		),
	];

	if ( skipped ) {
		parts.push(
			sprintf(
				/* translators: %d: Number of unknown settings skipped. */
				_n( '%d unknown key skipped.', '%d unknown keys skipped.', skipped, 'gatherpress' ),
				skipped,
			),
		);
	}

	if ( blocked ) {
		parts.push(
			sprintf(
				/* translators: %d: Number of settings that can't be imported. */
				_n(
					'%d setting cannot be imported and was left as it is.',
					'%d settings cannot be imported and were left as they are.',
					blocked,
					'gatherpress',
				),
				blocked,
			),
		);
	}

	return parts.join( ' ' );
}

/**
 * Download data as a JSON file.
 *
 * @since TBD
 *
 * @param {Object} data The data to download.
 *
 * @return {void}
 */
export function downloadJson( data ) {
	const blob = new Blob( [ JSON.stringify( data, null, 2 ) ], {
		type: 'application/json',
	} );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );

	link.href = url;
	link.download = `gatherpress-settings-${ new Date()
		.toISOString()
		.slice( 0, 10 ) }.json`;
	document.body.append( link );
	link.click();
	link.remove();
	URL.revokeObjectURL( url );
}

/**
 * Export the settings and download them.
 *
 * @since TBD
 *
 * @param {Object}      elements        The Tools elements.
 * @param {string}      elements.scope  Storage scope: `blog` or `network`.
 * @param {HTMLElement} elements.status The status output.
 *
 * @return {Promise<void>} Resolves once the request has settled.
 */
export async function exportSettings( { scope, status } ) {
	try {
		downloadJson(
			await apiFetch( {
				path: addQueryArgs( `${ SETTINGS_PATH }/export`, { scope } ),
			} ),
		);
	} catch ( apiError ) {
		showStatus(
			status,
			apiError?.message || __( 'Export failed.', 'gatherpress' ),
			false,
		);
	}
}

/**
 * Read the chosen import file and preview it.
 *
 * @since TBD
 *
 * @param {Object}           elements                The Tools elements.
 * @param {HTMLInputElement} elements.file           The file input.
 * @param {HTMLElement}      elements.preview        The preview box.
 * @param {HTMLElement}      elements.previewContent The preview content element.
 * @param {HTMLElement}      elements.importButton   The Import Settings button.
 *
 * @return {Promise<void>} Resolves once the file is read.
 */
export async function previewImport( elements ) {
	const { file, preview, previewContent, importButton } = elements;
	const chosen = file.files[ 0 ];
	const request = ++latestPreview;

	elements.importData = null;
	importButton.disabled = true;

	if ( ! chosen ) {
		preview.classList.add( HIDDEN_CLASS );
		return;
	}

	let data = null;

	try {
		data = JSON.parse( await chosen.text() );
	} catch {
		// Shown as an invalid file below.
	}

	// Another file was chosen while this one was read, and that one wins.
	if ( request !== latestPreview ) {
		return;
	}

	if ( data ) {
		elements.importData = data;
		renderPreview( previewContent, data );
		importButton.disabled = false;
	} else {
		const error = document.createElement( 'p' );

		error.className = 'gatherpress-settings-tools__error';
		error.textContent = __( 'Invalid JSON file.', 'gatherpress' );
		previewContent.replaceChildren( error );
	}

	preview.classList.remove( HIDDEN_CLASS );
}

/**
 * Check if a button is currently busy (marked with aria-disabled="true").
 *
 * @since TBD
 *
 * @param {HTMLElement|null} button The button element to check.
 *
 * @return {boolean} True if button is busy, false otherwise.
 */
export function isButtonBusy( button ) {
	return 'true' === button?.getAttribute( 'aria-disabled' );
}

/**
 * Toggle the busy state of a button using aria-disabled to preserve keyboard focus.
 *
 * @since TBD
 *
 * @param {HTMLElement|null} button The button element.
 * @param {boolean}          busy   Whether the button is busy.
 *
 * @return {void}
 */
export function setButtonBusy( button, busy ) {
	if ( ! button ) {
		return;
	}

	if ( busy ) {
		button.setAttribute( 'aria-disabled', 'true' );
	} else {
		button.removeAttribute( 'aria-disabled' );
	}
}

/**
 * Import the previewed file.
 *
 * @since TBD
 *
 * @param {Object}            elements              The Tools elements.
 * @param {string}            elements.scope        Storage scope: `blog` or `network`.
 * @param {Object|null}       elements.importData   The parsed import file.
 * @param {HTMLElement}       elements.root         The Tools container.
 * @param {HTMLButtonElement} elements.importButton The Import Settings button.
 * @param {HTMLElement}       elements.status       The status output.
 *
 * @return {Promise<void>} Resolves once the request has settled.
 */
export async function importSettings( elements ) {
	const { scope, importData, root, importButton, status } = elements;

	if ( ! importData || isButtonBusy( importButton ) ) {
		return;
	}

	setButtonBusy( importButton, true );
	showStatus( status, __( 'Importing settings…', 'gatherpress' ), true );

	try {
		const result = await apiFetch( {
			path: `${ SETTINGS_PATH }/import`,
			method: 'POST',
			data: {
				scope,
				mode: root.querySelector( '[name="gatherpress_import_mode"]:checked' )
					.value,
				import: importData,
			},
		} );

		showStatus( status, describeImport( result ), false );
	} catch ( apiError ) {
		showStatus(
			status,
			apiError?.message || __( 'Import failed.', 'gatherpress' ),
			false,
		);
	} finally {
		setButtonBusy( importButton, false );
	}
}

/**
 * Format bytes into a human-readable string.
 *
 * @since TBD
 *
 * @param {number} bytes Number of bytes.
 *
 * @return {string} Formatted size string.
 */
export function formatBytes( bytes ) {
	if ( 0 >= bytes ) {
		return '0 B';
	}

	const units = [ 'B', 'KB', 'MB', 'GB' ];
	const i = Math.min(
		Math.floor( Math.log( bytes ) / Math.log( 1024 ) ),
		units.length - 1,
	);
	const val = ( bytes / Math.pow( 1024, i ) ).toFixed( 0 === i ? 0 : 1 );

	return `${ val } ${ units[ i ] }`;
}

/**
 * Clean up unused venue static-map images.
 *
 * @since TBD
 *
 * @param {Object}      elements               Tools element references.
 * @param {HTMLElement} elements.cleanupButton The cleanup trigger button.
 * @param {HTMLElement} elements.cleanupStatus The status output element.
 *
 * @return {Promise<void>}
 */
export async function cleanupMaps( { cleanupButton, cleanupStatus } ) {
	if ( ! cleanupButton || ! cleanupStatus || isButtonBusy( cleanupButton ) ) {
		return;
	}

	setButtonBusy( cleanupButton, true );
	showStatus(
		cleanupStatus,
		__( 'Cleaning up unused static maps…', 'gatherpress' ),
		true,
	);

	try {
		const result = await apiFetch( {
			path: `/${ REST_NAMESPACE }/venue-map/gc`,
			method: 'POST',
			data: {
				execute: true,
			},
		} );

		const deleted = result?.deleted ?? 0;
		const bytesReclaimed = result?.bytes_reclaimed ?? 0;

		const message =
			0 < deleted
				? sprintf(
					/* translators: 1: Number of files deleted, 2: Formatted byte size reclaimed. */
					_n(
						'Cleanup complete: removed %1$d file (%2$s reclaimed).',
						'Cleanup complete: removed %1$d files (%2$s reclaimed).',
						deleted,
						'gatherpress',
					),
					deleted,
					formatBytes( bytesReclaimed ),
				)
				: __( 'No unused static-map images found.', 'gatherpress' );

		showStatus( cleanupStatus, message, false );
	} catch ( apiError ) {
		showStatus(
			cleanupStatus,
			apiError?.message || __( 'Cleanup failed.', 'gatherpress' ),
			false,
		);
	} finally {
		setButtonBusy( cleanupButton, false );
	}
}

/**
 * Wire up import and export on the Tools settings tab.
 *
 * Does nothing on pages without the Tools markup.
 *
 * @since TBD
 *
 * @return {void}
 */
export function initSettingsTools() {
	const root = document.querySelector(
		'[data-gatherpress_component_name="settings-tools"]',
	);

	if ( ! root ) {
		return;
	}

	const elements = {
		root,
		scope: root.dataset.gatherpress_scope,
		importData: null,
		file: root.querySelector( '#gatherpress-import-file' ),
		preview: root.querySelector( '.gatherpress-settings-tools__preview' ),
		previewContent: root.querySelector(
			'.gatherpress-settings-tools__preview-content',
		),
		importButton: root.querySelector( '.gatherpress-settings-tools__import' ),
		status: root.querySelector( '.gatherpress-settings-tools__status' ),
	};

	root.querySelector( '.gatherpress-settings-tools__export' ).addEventListener(
		'click',
		() => exportSettings( elements ),
	);
	elements.file.addEventListener( 'change', () => previewImport( elements ) );
	elements.importButton.addEventListener( 'click', () =>
		importSettings( elements ),
	);

	const cleanupButton = root.querySelector(
		'.gatherpress-settings-tools__cleanup-maps',
	);
	const cleanupStatus = root.querySelector(
		'.gatherpress-settings-tools__cleanup-status',
	);

	if ( cleanupButton && cleanupStatus ) {
		cleanupButton.addEventListener( 'click', () =>
			cleanupMaps( { cleanupButton, cleanupStatus } ),
		);
	}
}
