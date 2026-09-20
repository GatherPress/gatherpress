/**
 * ESLint flat config.
 *
 * `wp-scripts lint-js` only reads flat config, and falls back to the config
 * bundled with @wordpress/scripts when a project has none. That default turns
 * on Prettier, which formats against WordPress's own spacing, so the rules
 * this project has always linted with are spelled out here instead.
 *
 * The plugin is resolved through @wordpress/scripts rather than declared
 * again, so the linter and its rules always come from one place.
 */

/**
 * External dependencies
 */
const path = require( 'path' );

const scriptsDir = path.dirname(
	require.resolve( '@wordpress/scripts/package.json' ),
);
const requireFromScripts = ( name ) =>
	require( require.resolve( name, { paths: [ scriptsDir ] } ) );

const wpPlugin = requireFromScripts( '@wordpress/eslint-plugin' );
const globals = requireFromScripts( 'globals' );

module.exports = [
	{
		ignores: [
			// Dot directories were outside ESLint's reach under eslintrc, and
			// the CI helper scripts in here are Node, not plugin source.
			'.github/**',
			'build/**',
			'coverage/**',
			'node_modules/**',
			'playwright-report/**',
			'test-results/**',
			'vendor/**',
		],
	},
	...wpPlugin.configs[ 'recommended-with-formatting' ],
	{
		languageOptions: {
			globals: {
				...globals.browser,
			},
		},
		rules: {
			'@wordpress/i18n-text-domain': [
				'error',
				{ allowedTextDomain: 'gatherpress' },
			],
			'jsdoc/no-undefined-types': [
				'error',
				{ definedTypes: [ 'JSX', 'Component' ] },
			],
			yoda: [ 'error', 'always', { exceptRange: true } ],
		},
	},
];
