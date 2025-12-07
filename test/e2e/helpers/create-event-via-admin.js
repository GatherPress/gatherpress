/**
 * Playwright helper to create a GatherPress event with RSVP block via WordPress admin.
 *
 * This creates events through the WordPress admin UI, which ensures they're
 * properly accessible via HTTP (unlike wp-cli created posts which sometimes
 * have permalink/cache issues).
 *
 * @param {import('@playwright/test').Page} page - Playwright page object.
 * @return {Promise<string>} The event URL.
 */
async function createEventWithRSVP( page ) {
	const adminUrl = 'http://localhost:8889/wp-admin';

	// Navigate to new event page.
	await page.goto( `${ adminUrl }/post-new.php?post_type=gatherpress_event` );
	await page.waitForLoadState( 'load' );

	// Dismiss any modals that may appear (welcome guide, etc).
	// The simplest approach is to press Escape multiple times.
	await page.waitForTimeout( 1000 );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 300 );

	// Wait for editor to be ready.
	await page.waitForSelector( '.edit-post-layout, .editor-styles-wrapper' );

	// Set event title.
	const titleSelector = '.editor-post-title__input, [aria-label="Add title"]';
	await page.waitForSelector( titleSelector );
	await page.fill( titleSelector, 'E2E Test Event with RSVP' );

	// Set event datetime (7 days in the future).
	const futureDate = new Date();
	futureDate.setDate( futureDate.getDate() + 7 );

	// Look for GatherPress datetime controls in the sidebar.
	// First, ensure the event settings panel is open.
	const eventSettingsButton = page.locator( 'button:has-text("Event settings")' );
	if ( await eventSettingsButton.count() > 0 ) {
		const isExpanded = await eventSettingsButton.getAttribute( 'aria-expanded' );
		if ( isExpanded !== 'true' ) {
			await eventSettingsButton.click();
		}
	}

	// Add RSVP block to the event.
	// Click the "Add block" button in the editor.
	await page.click( '.edit-post-header-toolbar__inserter-toggle, [aria-label="Toggle block inserter"]' );

	// Search for the RSVP block.
	const searchInput = page.locator( '.block-editor-inserter__search-input, .block-editor-inserter__search input' );
	await searchInput.fill( 'rsvp' );

	// Click the RSVP block option.
	const rsvpBlockOption = page.locator( '.block-editor-block-types-list__item:has-text("RSVP"), button:has-text("RSVP")' ).first();
	await rsvpBlockOption.click();

	// Wait a moment for the block to be inserted.
	await page.waitForTimeout( 1000 );

	// Publish the event.
	// Click the publish button in the header.
	const publishButton = page.locator( '.editor-post-publish-panel__toggle, button:has-text("Publish")' ).first();
	await publishButton.click();

	// Wait for the publish panel to open, then click the final publish button.
	const finalPublishButton = page.locator( '.editor-post-publish-button, button:has-text("Publish"):visible' ).last();
	await finalPublishButton.click();

	// Wait for the publish confirmation.
	await page.waitForSelector( '.components-snackbar, .post-publish-panel__postpublish' );

	// Get the event URL from the post publish panel or from the current page.
	let eventUrl;

	// Try to get the URL from the view post link.
	const viewPostLink = page.locator( 'a:has-text("View Event"), a:has-text("View Post")' ).first();
	if ( await viewPostLink.count() > 0 ) {
		eventUrl = await viewPostLink.getAttribute( 'href' );
	} else {
		// Fallback: get the post ID from the URL and construct the event URL.
		const currentUrl = page.url();
		const postIdMatch = currentUrl.match( /post=(\d+)/ );
		if ( postIdMatch ) {
			const postId = postIdMatch[ 1 ];
			eventUrl = `http://localhost:8889/?p=${ postId }`;
		}
	}

	if ( ! eventUrl ) {
		throw new Error( 'Could not determine event URL after publishing' );
	}

	// eslint-disable-next-line no-console
	console.log( `Created event via admin UI: ${ eventUrl }` );

	return eventUrl;
}

module.exports = { createEventWithRSVP };
