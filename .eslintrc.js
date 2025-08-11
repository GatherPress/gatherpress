module.exports = {
	root: true,
	extends: [
		'plugin:@wordpress/eslint-plugin/recommended',
		'plugin:eslint-comments/recommended',
	],
	ignorePatterns: [
		'node_modules/',
		'build/',
		'coverage/',
		'playwright-report/',
		'test-results/',
		'vendor/',
		'wp-core/',
	],
	globals: {
		wp: 'off',
		globalThis: 'readonly',
	},
	env: {
		browser: true,
		es2020: true,
		node: true,
	},
	parserOptions: {
		ecmaFeatures: {
			jsx: true,
		},
		ecmaVersion: 12,
		sourceType: 'module',
	},
	plugins: [ 'react' ],
	settings: {
		jsdoc: {
			mode: 'typescript',
		},
		'import/resolver': {
			node: {
				extensions: [ '.js', '.jsx', '.ts', '.tsx' ],
			},
		},
	},
	rules: {
		'jest/expect-expect': 'off',
		'react/jsx-boolean-value': 'error',
		'@wordpress/dependency-group': 'error',
		'@wordpress/react-no-unsafe-timeout': 'error',
		'@wordpress/i18n-text-domain': [
			'error',
			{
				allowedTextDomain: 'gatherpress',
			},
		],
		'@wordpress/no-unsafe-wp-apis': 'off',
		'import/default': 'error',
		'import/named': 'error',
		'no-restricted-imports': [
			'error',
			{
				paths: [
					{
						name: 'react',
						message:
							'Please use React API through `@wordpress/element` instead.',
					},
					{
						name: 'lodash',
						importNames: [ 'memoize' ],
						message: 'Please use `memize` instead.',
					},
				],
			},
		],
		'no-restricted-syntax': [
			'error',
			{
				selector:
					'CallExpression[callee.name="Math"][callee.property.name="random"]',
				message:
					'Do not use Math.random() to generate unique IDs; use withInstanceId instead.',
			},
			{
				selector:
					'CallExpression[callee.name="withDispatch"] > :function > BlockStatement > :not(VariableDeclaration,ReturnStatement)',
				message:
					'withDispatch must return an object with consistent keys. Avoid performing logic in `mapDispatchToProps`.',
			},
		],
		// WordPress whitespace/formatting rules
		'array-bracket-spacing': [ 'error', 'always' ],
		'object-curly-spacing': [ 'error', 'always' ],
		'space-in-parens': [ 'error', 'always', { exceptions: [ 'empty' ] } ],
		'template-curly-spacing': [ 'error', 'always' ],
		'computed-property-spacing': [ 'error', 'always' ],
		'space-before-blocks': [ 'error', 'always' ],
		'space-before-function-paren': [
			'error',
			{
				anonymous: 'never',
				named: 'never',
				asyncArrow: 'always',
			},
		],
		'space-infix-ops': 'error',
		'key-spacing': 'error',
		'comma-spacing': 'error',
		'keyword-spacing': 'error',
		indent: [ 'error', 'tab', { SwitchCase: 1 } ],
		// JSX-specific spacing rules
		'react/jsx-curly-spacing': [ 'error', 'always' ],
		'react/jsx-tag-spacing': [
			'error',
			{
				closingSlash: 'never',
				beforeSelfClosing: 'always',
				afterOpening: 'never',
				beforeClosing: 'never',
			},
		],
		// Disable Prettier rules that conflict with WordPress standards
		'prettier/prettier': 'off',
	},
	overrides: [
		{
			files: [ '**/*.test.js', '**/*.spec.js', '**/test/**/*.js' ],
			extends: [ 'plugin:@wordpress/eslint-plugin/test-unit' ],
		},
		{
			files: [ '**/e2e/**/*.js' ],
			extends: [ 'plugin:@wordpress/eslint-plugin/test-e2e' ],
		},
		{
			files: [ 'webpack.config.js', '*.config.js' ],
			env: {
				node: true,
			},
		},
	],
};
