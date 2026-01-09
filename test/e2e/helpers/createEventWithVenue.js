/**
 * Create GatherPress Event and attach Venue via admin UI
 * @param {import('@playwright/test').Page} page
 * @param {string}                          venueTitle
 * @return {Promise<string>} eventUrl
 */
async function createEventWithVenue( page, venueTitle ) {
	const adminUrl = 'http://localhost:8889/wp-admin';

	await page.goto( `${ adminUrl }/post-new.php?post_type=gatherpress_event` );
	await page.waitForLoadState( 'load' );

	// Dismiss modals
	await page.waitForTimeout( 800 );
	await page.keyboard.press( 'Escape' );
	await page.keyboard.press( 'Escape' );

	// Title
	await page.waitForSelector( '.editor-post-title__input, [aria-label="Add title"]' );
	await page.fill(
		'.editor-post-title__input, [aria-label="Add title"]',
		'E2E Event With Venue'
	);

	// Open Event settings
	const eventSettings = page.locator( 'button:has-text("Event settings")' );
	if ( await eventSettings.count() ) {
		if ( 'true' !== await eventSettings.getAttribute( 'aria-expanded' ) ) {
			await eventSettings.click();
		}
	}

	const venueSelect = page
		.locator( 'select.components-select-control__input' )
		.filter( { hasText: 'Choose a venue' } ) // ensures correct select
		.first();

	// Wait until the option with our venue title appears
	await page.waitForFunction(
		( { title } ) => {
			const selects = document.querySelectorAll(
				'select.components-select-control__input'
			);
			return Array.from( selects ).some( ( select ) =>
				Array.from( select.options ).some( ( opt ) =>
					opt.textContent.trim() === title
				)
			);
		},
		{ title: venueTitle }
	);

	// Extract the option value for the venue
	const venueValue = await venueSelect.evaluate(
		( select, title ) => {
			const option = Array.from( select.options ).find(
				( opt ) => opt.textContent.trim() === title
			);
			return option?.value;
		},
		venueTitle
	);

	if ( ! venueValue ) {
		throw new Error( `Venue option not found: ${ venueTitle }` );
	}

	// Select by value (stable)
	await venueSelect.selectOption( { value: venueValue } );

	// Publish
	const publishToggle = page.locator(
		'button.editor-post-publish-panel__toggle'
	);
	await publishToggle.click();

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

	// Event URL
	const viewLink = page.locator( 'a:has-text("View Event"), a:has-text("View Post")' ).first();
	if ( await viewLink.count() ) {
		const eventUrl = await viewLink.getAttribute( 'href' );
		return eventUrl;
	}

	const postIdMatch = page.url().match( /post=(\d+)/ );
	return `http://localhost:8889/?p=${ postIdMatch[ 1 ] }`;
}

module.exports = { createEventWithVenue };
