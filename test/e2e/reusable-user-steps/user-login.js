/**
 * This file will contain common user steps that may be required in multiple different tests
 */
/**
 * Login user flow
 *
 * @param {*} root0
 * @param {*} root0.page
 * @param {*} root0.username
 * @param {*} root0.password
 */
const loginUser = async ({
	page,
	username,
	password = process.env.WP_ADMIN_PASSWORD,
}) => {
	await page.getByLabel('Username or Email Address').isVisible();
	await page.getByLabel('Username or Email Address').fill(username);

	await page.getByLabel('Password', { exact: true }).isVisible();
	await page.getByLabel('Password', { exact: true }).fill(password);

	await page.getByRole('button', { name: 'Log In' }).click();
};

module.exports = { loginUser };
