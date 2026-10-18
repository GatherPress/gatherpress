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

/**
 * Show a status line under the Import Settings button.
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
	status.classList.remove( HIDDEN_CLASS );
	status.querySelector( '.spinner' ).classList.toggle( 'is-active', busy );
	status.querySelector( '.gatherpress-settings-tools__status-text' ).textContent =
		text;
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

	elements.importData = null;
	importButton.disabled = true;

	if ( ! chosen ) {
		preview.classList.add( HIDDEN_CLASS );
		return;
	}

	try {
		elements.importData = JSON.parse( await chosen.text() );
		renderPreview( previewContent, elements.importData );
		importButton.disabled = false;
	} catch {
		const error = document.createElement( 'p' );

		error.className = 'gatherpress-settings-tools__error';
		error.textContent = __( 'Invalid JSON file.', 'gatherpress' );
		previewContent.replaceChildren( error );
	}

	preview.classList.remove( HIDDEN_CLASS );
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

	if ( ! importData ) {
		return;
	}

	importButton.disabled = true;
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
		importButton.disabled = false;
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
}
