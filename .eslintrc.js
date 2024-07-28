module.exports = {
	extends: ['plugin:@wordpress/eslint-plugin/recommended'],
	ignorePatterns: [
		'node_modules/',
		'build/',
		'coverage/',
		'playwright-report/',
		'test-results/',
		'vendor/',
		'wp-core/',
	],
	env: {
		browser: true,
		es2020: true,
	},
	parserOptions: {
		ecmaFeatures: {
			jsx: true,
		},
		ecmaVersion: 12,
		sourceType: 'module',
	},
	plugins: ['react'],
	rules: {},
};
