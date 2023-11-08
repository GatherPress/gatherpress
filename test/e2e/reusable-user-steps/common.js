/**
 * Login user flow
 * @param {*} page
 * @param {*} username
 * @param {*} password
 */
const login = async ({ page, username, password = process.env.WP_ADMIN_PASSWORD }) => {
	page.goto("/wp-login.php", {
		timeout: 40000,
	});

	await page.getByLabel("Username or Email Address").isVisible();
	await page.getByLabel("Username or Email Address").fill(username);

	await page.getByLabel("Password", { exact: true }).isVisible();

	await page
		.getByLabel("Password", { exact: true })
		.fill(password);

	await page.getByRole("button", { name: "Log In" }).click();
};

module.exports = { login };
