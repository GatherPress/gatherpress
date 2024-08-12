/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const GPOOV_CLASS_NAME   = 'gp-onlineevent-or-venue';

test.describe( 'GatherPress general block tests', () => {
/* 
	test.beforeAll(async ({ requestUtils }) => {
        // await requestUtils.activatePlugin('gatherpress');

		// TEST // DO NOT MERGE // should make: 1. test fail, 2. test pass
        // await requestUtils.deactivatePlugin('gatherpress');
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
	test( 'Is 1 block available?', async ( {
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
// THIS IS COPIED FROM //
// https://github.com/WordPress/gutenberg/blob/c48075b6665ec3910d00677088672c1ba9e24916/test/e2e/specs/editor/blocks/paragraph.spec.js#L26C1-L40C43
// FOR DEBUGGING ONLY //
// REMOVE AFTER WORKFLOW RUNS //

		await editor.insertBlock( {
			name: 'core/paragraph',
		} );
		await page.keyboard.type( '1' );

		const firstBlockTagName = await editor.canvas
			.locator( ':root' )
			.evaluate( () => {
				return document.querySelector( '[data-block]' ).tagName;
			} );

		// The outer element should be a paragraph. Blocks should never have any
		// additional div wrappers so the markup remains simple and easy to
		// style.
		expect( firstBlockTagName ).toBe( 'P' );
// END // THIS IS COPIED FROM //
// https://github.com/WordPress/gutenberg/blob/c48075b6665ec3910d00677088672c1ba9e24916/test/e2e/specs/editor/blocks/paragraph.spec.js#L26C1-L40C43
// FOR DEBUGGING ONLY //
// REMOVE AFTER WORKFLOW RUNS //


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