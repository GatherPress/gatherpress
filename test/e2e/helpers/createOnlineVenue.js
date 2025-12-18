/**
 * Create a GatherPress Venue via WordPress admin UI
 * @param {import('@playwright/test').Page} page
 * @return {Promise<{ venueId: string, venueTitle: string, venueUrl: string }>}
 * An object containing the created venue ID, title, and public URL
 */
async function createVenue( page ) {
	const adminUrl = 'http://localhost:8889/wp-admin';
	const venueTitle = 'online Venue';

	await page.goto( `${ adminUrl }/post-new.php?post_type=gatherpress_venue` );
	await page.waitForLoadState( 'load' );

	// Dismiss Gutenberg modals
	await page.waitForTimeout( 800 );
	await page.keyboard.press( 'Escape' );
	await page.keyboard.press( 'Escape' );

	// Wait for editor
	await page.waitForSelector( '.editor-post-title__input, [aria-label="Add title"]' );

	// Venue title
	await page.fill(
		'.editor-post-title__input, [aria-label="Add title"]',
		venueTitle
	);

	// Open Venue settings panel
	const venueSettings = page.locator( 'button:has-text("Venue settings")' );
	if ( await venueSettings.count() ) {
		if ( 'true' !== await venueSettings.getAttribute( 'aria-expanded' ) ) {
			await venueSettings.click();
		}
	}

	// Venue location (fallback-safe selector)
	const onlineVenue = page.locator(
		'input[id="inspector-text-control-2"]'
	).first();

	await onlineVenue.fill( 'https://meet.google.com/' );

	// Publish
	// Publish
	const publishButton = page
		.locator( '.editor-post-publish-panel__toggle, button:has-text("Publish")' )
		.first();

	await page.waitForSelector(
		'.components-modal__screen-overlay',
		{ state: 'hidden', timeout: 15000 }
	);

	await publishButton.click( { force: true } );

	// Wait for final Publish button
	const finalPublish = page.locator(
		'button.editor-post-publish-button__button.is-primary'
	).last();

	await finalPublish.waitFor( { state: 'visible' } );
	await finalPublish.click( { force: true } );

	// Give Gutenberg a moment to remove overlay
	await page.waitForTimeout( 500 );

	// Click the real publish button (overlay-safe)
	await finalPublish.click( { force: true } );

	// Wait for confirmation
	await page.waitForSelector(
		'.editor-post-publish-panel__postpublish, .components-snackbar'
	);

	await page.waitForSelector(
		'.components-snackbar, .post-publish-panel__postpublish'
	);

	// Extract venue ID
	const postIdMatch = page.url().match( /post=(\d+)/ );
	if ( ! postIdMatch ) {
		throw new Error( 'Could not determine venue ID' );
	}
	const venueId = postIdMatch[ 1 ];

	// Venue URL
	let venueUrl;
	const viewLink = page.locator( 'a:has-text("View Venue"), a:has-text("View Post")' ).first();
	if ( await viewLink.count() ) {
		venueUrl = await viewLink.getAttribute( 'href' );
	} else {
		venueUrl = `http://localhost:8889/?p=${ venueId }`;
	}
	return { venueId, venueTitle, venueUrl };
}

module.exports = { createVenue };
