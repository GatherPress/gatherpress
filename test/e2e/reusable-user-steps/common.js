/**
 * Login user flow
 * @param {*} page
 * @param {*} username
 * @param {*} password
 */
const login = async ({ page, username, password = process.env.WP_ADMIN_PASSWORD }) => {
	page.goto("https://develop.gatherpress.org/wp-admin", {
		timeout: 40000,
	});

	await page.getByLabel("Username or Email Address").isVisible();
	await page.getByLabel("Username or Email Address").fill(username);

	await page.getByLabel("Password", { exact: true }).isVisible();

	await page
		.getByLabel("Password", { exact: true })
		.fill("zm86079&volj&!R5maIWEYX4");

	await page.getByRole("button", { name: "Log In" }).click();
};

module.exports = { login };
