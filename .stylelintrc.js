module.exports = {
	extends: [
		'stylelint-config-standard',
		'stylelint-config-recommended',
		'stylelint-config-recommended-scss'
	],
	plugins: ['stylelint-scss'],
	ignoreFiles: [
		'node_modules/**/*',
		'build/**/*',
		'coverage/**/*',
		'playwright-report/**/*',
		'test-results/**/*',
		'vendor/**/*',
		'wp-core/**/*',
	],
	rules: {
		'import-notation': null,
		'color-no-invalid-hex': true,
		'font-family-no-duplicate-names': true,
		'function-calc-no-unspaced-operator': true,
		'string-no-newline': true,
		'block-no-empty': null,
		'unit-no-unknown': true,
		'property-no-unknown': [true, {
			ignoreProperties: ['/^--wp--preset--/']
		}],
		'custom-property-pattern': null,
		'at-rule-no-unknown': null,
		'scss/at-rule-no-unknown': true,
		'no-descending-specificity': null,
		'selector-class-pattern': null,
	},
};
