/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
/**
 * internal dependencies
 */
// import { test, expect, WP_AUTH_STORAGE } from '@test-utils';
import { WP_AUTH_STORAGE } from '@test-utils';

const GPOOV_CLASS_NAME   = 'gp-onlineevent-or-venue';

test.describe( 'GatherPress general block tests', () => {
    // We have multiple tests in this file, all requiring us to be authenticated.
    // Compare this to the front-end.spec.ts.
    // test.use({ storageState: WP_AUTH_STORAGE });
    
/*     test.beforeAll(async ({ requestUtils }) => {
        await requestUtils.activatePlugin('gatherpress');
    }); */

    test.beforeEach( async ( { admin } ) => {
		// await admin.createNewPost( { postType: 'gatherpress_event' } );
		await admin.createNewPost();
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	/**
	 * Are 3 blocks available?
	 *
	 * Tests if all blocks are avail through the block inserter panel.
	 *
	 * Adopted from 'Search for the Paragraph block with 2 additional variations'
	 * @source https://github.com/WordPress/gutenberg/blob/ddadd1a95d18270908ac4a1fd8d6e354cfadf61c/test/e2e/specs/editor/plugins/block-variations.spec.js#L62
	 */
	test( 'Are 1 blocks available?', async ( {
		page,
	} ) => {
		await page
			.getByRole( 'button', { name: 'Toggle block inserter' } )
			.click();

		await page
			.getByRole( 'region', { name: 'Block Library' } )
			.getByRole( 'searchbox', {
				name: 'Search for blocks and patterns',
			} )
			.fill( 'gatherpress' );

		await expect(
			page
				.getByRole( 'listbox', { name: 'Blocks' } )
				.getByRole( 'option' )
		).toHaveText( [ 'Events List' ] );
	} );

	test( 'Does the block insert?', async ( {
		page,
		editor,
	} ) => {
/* 
		await editor.insertBlock( { name: 'pseudo-' + GPOOV_CLASS_NAME + '-button' } );

        expect( await page.$('.' + GPOOV_CLASS_NAME ) ).not.toBeNull();

		const block = editor.canvas.getByRole( 'document', {
			name: 'Online-Event Link',
		} );

        await expect( block ).not.toBeNull(); */

/*
		await editor.canvas
            .getByLabel('Add default block')
			.click();

		await page.keyboard.type( '/Online-Event Link' );
		await page.keyboard.press( 'Enter' );

		await expect(
			editor.canvas.getByRole( 'document', { name: 'Online-Event Link' } )
		).toHaveText( 'Online-Event' ); */
    } );

} );