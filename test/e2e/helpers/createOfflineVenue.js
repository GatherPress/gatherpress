/**
 * Create a GatherPress Venue via WordPress admin UI
 * @param {import('@playwright/test').Page} page
 * @return {Promise<{ venueId: string, venueTitle: string, venueUrl: string }>}
 * An object containing the created venue ID, title, and public URL
 */
async function createVenue( page ) {
	const adminUrl = 'http://localhost:8889/wp-admin';
	const venueTitle = 'offline Venue';

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
	const locationInput = page.locator(
		'input[aria-label*="Location"], input[id="inspector-text-control-0"]'
	).first();

	await locationInput.fill( 'Amravati, Maharashtra' );

	// Publish
	// Click the publish button in the header.
	const publishButton = page.locator( '.editor-post-publish-panel__toggle, button:has-text("Publish")' ).first();
	await publishButton.click();

	// Wait for the final Publish button inside the panel
	const finalPublish = page.locator(
		'button.editor-post-publish-button__button.is-primary'
	).last();

	await finalPublish.waitFor( { state: 'visible' } );

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
