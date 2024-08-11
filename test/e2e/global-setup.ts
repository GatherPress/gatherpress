/**
 * External dependencies
 */
const path = require( 'path' );
import { request } from '@playwright/test';

/**
 * WordPress dependencies
 */
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 * 
 * @TODO: Would be nice to require() those constants directly from gutenberg, but they are not publicly exposed.
 */
// const { WP_BASE_URL, WP_USERNAME, WP_PASSWORD } = require( '@wordpress/e2e-test-utils-playwright/config' );
import { WP_BASE_URL, WP_USERNAME, WP_PASSWORD, WP_AUTH_STORAGE } from '@test-utils';

const STORAGE_STATE_PATH = process.env.STORAGE_STATE_PATH ||
    path.join(process.cwd(), 'artifacts/storage-states/admin.json');

// To interact with the GatherPress plugin's settings page, we must be authenticated.
// Before any tests are run, we sign in, save the cookies set by WordPress, and then discard the session.
// Later, when we need to act as a logged-in user, we make those cookies available.
// https://playwright.dev/docs/test-global-setup-teardown#configure-globalsetup-and-globalteardown
export default async function globalSetup() {
    const requestContext = await request.newContext({
        baseURL: WP_BASE_URL,
    });
    const requestUtils = new RequestUtils(requestContext, {
        // storageStatePath: WP_AUTH_STORAGE,
        storageStatePath: STORAGE_STATE_PATH,
        user: {
            username: WP_USERNAME,
            password: WP_PASSWORD,
        },
    });

    // Alternatively, we could take a more traditional route,
    // filling in the input fields for the username and password and submitting the form.
    // https://playwright.dev/docs/test-global-setup-teardown#example

    // Authenticate and save the storageState to disk.
    await requestUtils.setupRest();

    //
    await requestContext.dispose();
}