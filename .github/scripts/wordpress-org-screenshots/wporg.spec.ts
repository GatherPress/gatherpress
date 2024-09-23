/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Screenshots for the wordpress.org/plugins repository', () => {
	let
        language: string,
        local_code: string,
        latest_event_data = null;

    // set the file name of the screenshot basaed on the current locale
    // https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/#filenames-2
    const getFileName = ( title: string ) => {
        return [
            title,
            local_code,
            '.png'
        ].join('').toLowerCase();
    }


    test.beforeAll( async ( { requestUtils } ) => {

        const latest_event = await requestUtils.rest( {
            path: '/wp/v2/gatherpress_events',
            method: 'GET',
            params: {
                per_page: 1,
                status: 'publish'
            },
        } );

        if ( latest_event && Array.isArray( latest_event ) && latest_event.length > 0 ) {
            latest_event_data = latest_event[0];
        } else {
            console.warn('No event found or the response was invalid.');
        }

        // https://github.com/WordPress/gutenberg/blob/trunk/packages/e2e-test-utils-playwright/src/request-utils/site-settings.ts#L34-L35
		language = ( await requestUtils.getSiteSettings() ).language;
        console.log('language', language);
        local_code = ( 'en_US' === language ) ? '' : '-' + language.substring(0, 2);
	} );

    // The test-description should match the caption for screenshot-# in the readme.md
    test('Create a new event', async ({
        admin,
        editor,
        page,
    }) => {
        await admin.visitAdminPage(
            'post-new.php',
            'post_type=gatherpress_event'
        );

        await editor.setPreferences( 'core/edit-post', {
            welcomeGuide: false,
        });

        // Wait for 2 seconds
        await page.waitForTimeout(2000);

        // https://playwright.dev/docs/api/class-pageassertions#page-assertions-to-have-screenshot-1
        await expect(page).toHaveScreenshot( getFileName( 'screenshot-1' ), {
            fullPage: true
        });
    });

    // The test-description should match the caption for screenshot-# in the readme.md
    test('Edit an event', async ({
        admin,
        editor,
        page,
    }) => {
        if (latest_event_data) {

            await admin.visitAdminPage(
                'post.php',
                'action=edit&post=' + latest_event_data.id
            );
            
            await editor.setPreferences( 'core/edit-post', {
                welcomeGuide: false,
            });
            
            // Wait for 2 seconds
            await page.waitForTimeout(2000);
            
            // https://playwright.dev/docs/api/class-pageassertions#page-assertions-to-have-screenshot-1
            await expect(page).toHaveScreenshot( getFileName( 'screenshot-2' ), {
                fullPage: true
            });
        }
    });

    // The test-description should match the caption for screenshot-# in the readme.md
    test('Quick Edit an event', async ({
        admin,
        editor,
        page,
    }) => {
        if (latest_event_data) {

            await admin.visitAdminPage(
                'edit.php',
                'post_type=gatherpress_event'
            );
            const tr = await page.locator( '#post-' + latest_event_data.id );

            // Make Quick Edit visible.
            await expect(tr).toBeVisible();
            await tr.hover();

            // Open the Quick Edit panel for the last event.
            await tr.locator('css=button.editinline').click(); // Even a DOM based locator is discouraged, within WP, we can be relatively sure this class will be there for a while.

            // Wait for 2 seconds
            await page.waitForTimeout(2000);

            // https://playwright.dev/docs/api/class-pageassertions#page-assertions-to-have-screenshot-1
            await expect(page).toHaveScreenshot( getFileName( 'screenshot-3' ), {
                fullPage: true
            });
        }
    });

    // The test-description should match the caption for screenshot-# in the readme.md
    test('Create a new venue', async ({
        admin,
        editor,
        page,
    }) => {
        await admin.visitAdminPage(
            'post-new.php',
            'post_type=gatherpress_venue'
        );

        await editor.setPreferences( 'core/edit-post', {
            welcomeGuide: false,
        });

        // Wait for 2 seconds
        await page.waitForTimeout(2000);

        // https://playwright.dev/docs/api/class-pageassertions#page-assertions-to-have-screenshot-1
        await expect(page).toHaveScreenshot( getFileName( 'screenshot-4' ), {
            fullPage: true
        });
    });

    // The test-description should match the caption for screenshot-# in the readme.md
    test('General Settings', async ({
        page,
        admin,
    }) => {
        await admin.visitAdminPage(
            'edit.php',
            'post_type=gatherpress_event&page=gatherpress_general'
        );

        // Wait for 2 seconds
        await page.waitForTimeout(2000);

        // https://playwright.dev/docs/api/class-pageassertions#page-assertions-to-have-screenshot-1
        await expect(page).toHaveScreenshot( getFileName( 'screenshot-5' ), {
            fullPage: true
        });
    });

    // The test-description should match the caption for screenshot-# in the readme.md
    test('Leadership Settings', async ({
        page,
        admin,
    }) => {
        await admin.visitAdminPage(
            'edit.php',
            'post_type=gatherpress_event&page=gatherpress_leadership'
        );

        // Wait for 2 seconds
        await page.waitForTimeout(2000);

        // https://playwright.dev/docs/api/class-pageassertions#page-assertions-to-have-screenshot-1
        await expect(page).toHaveScreenshot( getFileName( 'screenshot-6' ), {
            fullPage: true
        });
    });

});