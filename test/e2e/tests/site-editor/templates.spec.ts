/**
 * WordPress dependencies
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Templates', () => {

	test('The events template can be edited', async ({
		page,
		admin,
	}) => {
		await admin.visitSiteEditor({
			// path: '', // http://localhost:8889/wp-admin/site-editor.php?
			postId: 'twentytwentyfour//single-gatherpress_event',
			postType: 'wp_template',
			canvas: 'edit',
			showWelcomeGuide: false,
		});

	});
});
