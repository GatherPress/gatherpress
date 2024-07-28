module.exports = {
	extends: ['stylelint-config-standard', 'stylelint-config-recommended'],
	ignoreFiles: [
		'node_modules/',
		'build/',
		'coverage/',
		'playwright-report/',
		'test-results/',
		'vendor/',
		'wp-core/',
	],
	rules: {
		// Add your stylelint rules here
		'color-no-invalid-hex': true,
		'font-family-no-duplicate-names': true,
		'function-calc-no-invalid': true,
		'string-no-newline': true,
		// You can add more rules as needed
	},
};
