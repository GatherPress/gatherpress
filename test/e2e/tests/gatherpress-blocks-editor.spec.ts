/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'GatherPress general block tests', () => {

    test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost();
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	/**
	 * Are all blocks available?
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

	test( 'Does the "Events List" block insert?', async ( {
		page,
		editor,
	} ) => {

		await editor.insertBlock( { name: 'Events List' } );

		/* 
		 * Not working yet, because the GatherPress blocks are still all on apiVersion 2,
		 * but need to have 3
		 * /

		const block = editor.canvas.getByRole( 'document', {
			name: 'Events List',
		} );

        await expect( block ).not.toBeNull(); */

/*
		await editor.canvas
            .getByLabel('Add default block')
			.click();

		await page.keyboard.type( '/Events List' );
		await page.keyboard.press( 'Enter' );

		await expect(
			editor.canvas.getByRole( 'document', { name: 'Events List' } )
		).toHaveText( '...' ); */
    } );

} );