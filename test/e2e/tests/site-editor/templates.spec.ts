/**
 * WordPress dependencies
 */
const { test } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Templates', () => {
	test('The events template can be edited', async ({ admin }) => {
		await admin.visitSiteEditor({
			postId: 'twentytwentyfour//single-gatherpress_event',
			postType: 'wp_template',
			canvas: 'edit',
			showWelcomeGuide: false,
		});
	});
});
