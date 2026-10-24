/**
 * External dependencies
 */
import {
	afterEach,
	beforeEach,
	describe,
	expect,
	it,
	jest,
} from '@jest/globals';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import {
	cleanupMaps,
	describeImport,
	downloadJson,
	exportSettings,
	formatBytes,
	importSettings,
	initSettingsTools,
	previewImport,
	renderPreview,
} from '@src/helpers/settings-tools';

jest.mock( '@wordpress/api-fetch' );

const HIDDEN_CLASS = 'gatherpress--is-hidden';

/**
 * Render the Tools markup the Tools template outputs.
 *
 * @param {string} scope Storage scope: `blog` or `network`.
 *
 * @return {HTMLElement} The Tools container.
 */
function renderTools( scope = 'blog' ) {
	document.body.innerHTML = `
		<div data-gatherpress_component_name="settings-tools" data-gatherpress_scope="${ scope }">
			<button type="button" class="gatherpress-settings-tools__export">Export</button>
			<input type="file" id="gatherpress-import-file" />
			<input type="radio" name="gatherpress_import_mode" value="merge" checked />
			<input type="radio" name="gatherpress_import_mode" value="replace" />
			<div class="gatherpress-settings-tools__preview ${ HIDDEN_CLASS }">
				<div class="gatherpress-settings-tools__preview-content"></div>
			</div>
			<button type="button" class="gatherpress-settings-tools__import" disabled>Import</button>
			<output class="gatherpress-settings-tools__status">
				<span class="spinner"></span>
				<span class="gatherpress-settings-tools__status-text"></span>
			</output>
			<button type="button" class="gatherpress-settings-tools__cleanup-maps">Clean up unused venue-map images</button>
			<output class="gatherpress-settings-tools__cleanup-status">
				<span class="spinner"></span>
				<span class="gatherpress-settings-tools__cleanup-status-text"></span>
			</output>
		</div>
	`;

	return document.querySelector(
		'[data-gatherpress_component_name="settings-tools"]',
	);
}

/**
 * Collect the Tools elements the way initSettingsTools() does.
 *
 * @param {HTMLElement} root The Tools container.
 *
 * @return {Object} The Tools elements.
 */
function getElements( root ) {
	return {
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
}

/**
 * Give the file input a chosen file that reads as the given text.
 *
 * @param {HTMLInputElement} input The file input.
 * @param {string|null}      text  The file's contents, or null for no file.
 *
 * @return {void}
 */
function chooseFile( input, text ) {
	Object.defineProperty( input, 'files', {
		configurable: true,
		value: null === text ? [] : [ { text: () => Promise.resolve( text ) } ],
	} );
}

/**
 * Read the status line's text.
 *
 * @param {Object} elements The Tools elements.
 *
 * @return {string} The status text.
 */
function statusText( elements ) {
	return elements.status.querySelector(
		'.gatherpress-settings-tools__status-text',
	).textContent;
}

describe( 'renderPreview', () => {
	it( 'lists the version, export date and setting keys', () => {
		const content = document.createElement( 'div' );

		renderPreview( content, {
			version: '0.37.0',
			exported_at: '2026-01-01T00:00:00Z',
			settings: { map_platform: 'osm', timezone: 'UTC' },
		} );

		expect( content.textContent ).toContain( 'Version: 0.37.0' );
		expect( content.textContent ).toContain( 'Exported:' );
		expect( content.textContent ).toContain( 'Settings: 2 values' );
		expect(
			Array.from( content.querySelectorAll( 'li code' ) ).map(
				( code ) => code.textContent,
			),
		).toEqual( [ 'map_platform', 'timezone' ] );
	} );

	it( 'shows file contents as text rather than markup', () => {
		const content = document.createElement( 'div' );

		renderPreview( content, {
			version: '<img src=x onerror=alert(1)>',
			settings: { '<b>key</b>': 1 },
		} );

		expect( content.querySelector( 'img' ) ).toBeNull();
		expect( content.querySelector( 'b' ) ).toBeNull();
		expect( content.textContent ).toContain( '<img src=x onerror=alert(1)>' );
		expect( content.querySelector( 'code' ).textContent ).toBe(
			'<b>key</b>',
		);
	} );

	it( 'skips the list when there are no settings, and anything missing', () => {
		const content = document.createElement( 'div' );

		content.textContent = 'stale';
		renderPreview( content, { settings: {} } );

		expect( content.textContent ).toBe( 'Settings: 0 values' );
		expect( content.querySelector( 'ul' ) ).toBeNull();

		renderPreview( content, {} );

		expect( content.textContent ).toBe( '' );
	} );
} );

describe( 'describeImport', () => {
	it( 'counts imported settings only when nothing was skipped', () => {
		expect( describeImport( { imported: [ 'a' ] } ) ).toBe(
			'1 setting imported.',
		);
		expect( describeImport( {} ) ).toBe( '0 settings imported.' );
	} );

	it( 'adds skipped and blocked settings', () => {
		expect(
			describeImport( {
				imported: [ 'a', 'b' ],
				skipped: [ 'c' ],
				not_importable: [ 'd', 'e' ],
			} ),
		).toBe(
			'2 settings imported. 1 unknown key skipped. 2 settings cannot be imported and were left as they are.',
		);
	} );
} );

describe( 'downloadJson', () => {
	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'downloads the data as a dated JSON file', () => {
		URL.createObjectURL = jest.fn( () => 'blob:settings' );
		URL.revokeObjectURL = jest.fn();

		const click = jest
			.spyOn( HTMLAnchorElement.prototype, 'click' )
			.mockImplementation( function() {
				expect( this.href ).toBe( 'blob:settings' );
				expect( this.download ).toMatch(
					/^gatherpress-settings-\d{4}-\d{2}-\d{2}\.json$/,
				);
			} );

		downloadJson( { settings: {} } );

		expect( click ).toHaveBeenCalledTimes( 1 );
		expect( URL.revokeObjectURL ).toHaveBeenCalledWith( 'blob:settings' );
		expect( document.querySelector( 'a' ) ).toBeNull();
	} );
} );

describe( 'exportSettings', () => {
	beforeEach( () => {
		URL.createObjectURL = jest.fn( () => 'blob:settings' );
		URL.revokeObjectURL = jest.fn();
	} );

	afterEach( () => {
		jest.restoreAllMocks();
		apiFetch.mockReset();
	} );

	it( 'requests the export for the page scope and downloads it', async () => {
		const elements = getElements( renderTools( 'network' ) );
		const click = jest
			.spyOn( HTMLAnchorElement.prototype, 'click' )
			.mockImplementation( () => {} );

		apiFetch.mockResolvedValue( { settings: {} } );

		await exportSettings( elements );

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/gatherpress/v1/settings/export?scope=network',
		} );
		expect( click ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'shows why the export failed', async () => {
		const elements = getElements( renderTools() );

		apiFetch.mockRejectedValueOnce( { message: 'Sorry, you are not allowed.' } );
		await exportSettings( elements );

		expect( statusText( elements ) ).toBe( 'Sorry, you are not allowed.' );
		expect( elements.status.classList.contains( HIDDEN_CLASS ) ).toBe( false );

		apiFetch.mockRejectedValueOnce( {} );
		await exportSettings( elements );

		expect( statusText( elements ) ).toBe( 'Export failed.' );
	} );
} );

describe( 'previewImport', () => {
	it( 'previews a valid file and enables the import', async () => {
		const elements = getElements( renderTools() );

		chooseFile( elements.file, '{"version":"0.37.0","settings":{"a":1}}' );
		await previewImport( elements );

		expect( elements.importData ).toEqual( {
			version: '0.37.0',
			settings: { a: 1 },
		} );
		expect( elements.importButton.disabled ).toBe( false );
		expect( elements.preview.classList.contains( HIDDEN_CLASS ) ).toBe( false );
		expect( elements.previewContent.textContent ).toContain( 'Version: 0.37.0' );
	} );

	it( 'reports a file that is not JSON', async () => {
		const elements = getElements( renderTools() );

		chooseFile( elements.file, 'not json{' );
		await previewImport( elements );

		expect( elements.importData ).toBeNull();
		expect( elements.importButton.disabled ).toBe( true );
		expect(
			elements.previewContent.querySelector(
				'.gatherpress-settings-tools__error',
			).textContent,
		).toBe( 'Invalid JSON file.' );
		expect( elements.preview.classList.contains( HIDDEN_CLASS ) ).toBe( false );
	} );

	it( 'ignores a slow read once a newer file is chosen', async () => {
		const elements = getElements( renderTools() );
		let finishOld;

		Object.defineProperty( elements.file, 'files', {
			configurable: true,
			value: [
				{
					text: () =>
						new Promise( ( resolve ) => {
							finishOld = resolve;
						} ),
				},
			],
		} );

		const oldRead = previewImport( elements );

		chooseFile( elements.file, '{"settings":{"new":1}}' );
		await previewImport( elements );

		finishOld( '{"settings":{"old":1}}' );
		await oldRead;

		expect( elements.importData ).toEqual( { settings: { new: 1 } } );
		expect( elements.previewContent.textContent ).toContain( 'new' );
		expect( elements.previewContent.textContent ).not.toContain( 'old' );
	} );

	it( 'hides the preview when the file is cleared', async () => {
		const elements = getElements( renderTools() );

		elements.preview.classList.remove( HIDDEN_CLASS );
		chooseFile( elements.file, null );
		await previewImport( elements );

		expect( elements.importData ).toBeNull();
		expect( elements.importButton.disabled ).toBe( true );
		expect( elements.preview.classList.contains( HIDDEN_CLASS ) ).toBe( true );
	} );
} );

describe( 'importSettings', () => {
	afterEach( () => {
		apiFetch.mockReset();
	} );

	it( 'does nothing without a previewed file', async () => {
		const elements = getElements( renderTools() );

		await importSettings( elements );

		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'imports with the picked mode and shows the result', async () => {
		const elements = getElements( renderTools( 'network' ) );

		elements.importData = { settings: { a: 1 } };
		elements.root.querySelector( '[value="replace"]' ).checked = true;
		apiFetch.mockResolvedValue( { imported: [ 'a' ] } );

		await importSettings( elements );

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/gatherpress/v1/settings/import',
			method: 'POST',
			data: {
				scope: 'network',
				mode: 'replace',
				import: { settings: { a: 1 } },
			},
		} );
		expect( statusText( elements ) ).toBe( '1 setting imported.' );
		expect( elements.importButton.disabled ).toBe( false );
	} );

	it( 'shows why the import failed', async () => {
		const elements = getElements( renderTools() );

		elements.importData = { version: '1.0.0' };

		apiFetch.mockRejectedValueOnce( {
			message: 'Invalid import file: missing settings data.',
		} );
		await importSettings( elements );

		expect( statusText( elements ) ).toBe(
			'Invalid import file: missing settings data.',
		);

		apiFetch.mockRejectedValueOnce( {} );
		await importSettings( elements );

		expect( statusText( elements ) ).toBe( 'Import failed.' );
		expect( elements.importButton.disabled ).toBe( false );
	} );
} );

describe( 'initSettingsTools', () => {
	afterEach( () => {
		jest.restoreAllMocks();
		apiFetch.mockReset();
	} );

	it( 'does nothing on pages without the Tools markup', () => {
		document.body.innerHTML = '<div></div>';

		expect( () => initSettingsTools() ).not.toThrow();
	} );

	it( 'wires export, preview and import to the page', async () => {
		const root = renderTools();

		URL.createObjectURL = jest.fn( () => 'blob:settings' );
		URL.revokeObjectURL = jest.fn();
		jest.spyOn( HTMLAnchorElement.prototype, 'click' ).mockImplementation(
			() => {},
		);
		apiFetch.mockResolvedValue( { imported: [] } );

		initSettingsTools();

		root.querySelector( '.gatherpress-settings-tools__export' ).click();
		await Promise.resolve();

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/gatherpress/v1/settings/export?scope=blog',
		} );

		const file = root.querySelector( '#gatherpress-import-file' );

		chooseFile( file, '{"settings":{}}' );
		file.dispatchEvent( new Event( 'change' ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		const importButton = root.querySelector(
			'.gatherpress-settings-tools__import',
		);

		expect( importButton.disabled ).toBe( false );

		importButton.click();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				path: '/gatherpress/v1/settings/import',
			} ),
		);

		const cleanupButton = root.querySelector(
			'.gatherpress-settings-tools__cleanup-maps',
		);

		cleanupButton.click();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				path: '/gatherpress/v1/venue-map/gc',
			} ),
		);
	} );

	describe( 'formatBytes', () => {
		it( 'formats 0 bytes correctly', () => {
			expect( formatBytes( 0 ) ).toBe( '0 B' );
			expect( formatBytes( -10 ) ).toBe( '0 B' );
		} );

		it( 'formats bytes, kilobytes, and megabytes', () => {
			expect( formatBytes( 500 ) ).toBe( '500 B' );
			expect( formatBytes( 1024 ) ).toBe( '1.0 KB' );
			expect( formatBytes( 1048576 ) ).toBe( '1.0 MB' );
		} );
	} );

	describe( 'cleanupMaps', () => {
		it( 'returns early if required elements are missing', async () => {
			await cleanupMaps( {} );
			await cleanupMaps( { cleanupButton: document.createElement( 'button' ) } );
			await cleanupMaps( { cleanupStatus: document.createElement( 'output' ) } );

			expect( apiFetch ).not.toHaveBeenCalled();
		} );

		it( 'reports cleaned up files and reclaimed bytes on success', async () => {
			const cleanupButton = document.createElement( 'button' );
			const cleanupStatus = document.createElement( 'output' );
			cleanupStatus.innerHTML =
				'<span class="spinner"></span><span class="gatherpress-settings-tools__cleanup-status-text"></span>';

			apiFetch.mockResolvedValue( {
				deleted: 3,
				bytes_reclaimed: 15360,
			} );

			await cleanupMaps( { cleanupButton, cleanupStatus } );

			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/gatherpress/v1/venue-map/gc',
				method: 'POST',
				data: { execute: true },
			} );

			const cleanupText = cleanupStatus.querySelector(
				'.gatherpress-settings-tools__cleanup-status-text',
			).textContent;

			expect( cleanupText ).toContain( 'Cleanup complete: removed 3 files' );
			expect( cleanupText ).toContain( '15.0 KB reclaimed' );
			expect( cleanupButton.disabled ).toBe( false );
		} );

		it( 'reports when no unused images are found', async () => {
			const cleanupButton = document.createElement( 'button' );
			const cleanupStatus = document.createElement( 'output' );
			cleanupStatus.innerHTML =
				'<span class="spinner"></span><span class="gatherpress-settings-tools__cleanup-status-text"></span>';

			apiFetch.mockResolvedValue( {
				deleted: 0,
				bytes_reclaimed: 0,
			} );

			await cleanupMaps( { cleanupButton, cleanupStatus } );

			const cleanupText = cleanupStatus.querySelector(
				'.gatherpress-settings-tools__cleanup-status-text',
			).textContent;

			expect( cleanupText ).toBe( 'No unused static-map images found.' );
		} );

		it( 'reports error on failure', async () => {
			const cleanupButton = document.createElement( 'button' );
			const cleanupStatus = document.createElement( 'output' );
			cleanupStatus.innerHTML =
				'<span class="spinner"></span><span class="gatherpress-settings-tools__cleanup-status-text"></span>';

			apiFetch.mockRejectedValue( new Error( 'Network error' ) );

			await cleanupMaps( { cleanupButton, cleanupStatus } );

			const cleanupText = cleanupStatus.querySelector(
				'.gatherpress-settings-tools__cleanup-status-text',
			).textContent;

			expect( cleanupText ).toBe( 'Network error' );
			expect( cleanupButton.disabled ).toBe( false );
		} );
	} );
} );
