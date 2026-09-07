const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,
	setupFiles: [
		...( defaultConfig.setupFiles || [] ),
		'<rootDir>/test/unit/js/jest.setup.js',
	],
	moduleNameMapper: {
		'\\.(png|jpg|jpeg|gif|webp)$':
			'<rootDir>/test/unit/js/__mocks__/fileMock.js',
		'^@src/(.*)$': '<rootDir>/src/$1',
		...defaultConfig.moduleNameMapper,
	},
	// These ship ESM only, so Jest has to transform them rather than skip
	// node_modules wholesale. The `.*` before the alternation matters: since
	// @wordpress/core-data 7.54 the ESM packages arrive nested
	// (node_modules/@wordpress/core-data/node_modules/@wordpress/theme), and a
	// lookahead anchored right after `node_modules/` never sees them.
	// The inherited transform only matches `.js`/`.ts(x)`, so the `.mjs` files
	// @wordpress/theme ships were left untransformed even once they were no
	// longer ignored below.
	transform: {
		...defaultConfig.transform,
		'\\.mjs$': require.resolve(
			'@wordpress/scripts/config/babel-transform'
		),
	},
	moduleFileExtensions: [ 'js', 'jsx', 'mjs', 'ts', 'tsx', 'json', 'node' ],
	transformIgnorePatterns: [
		'node_modules/(?!.*(?:parsel-js|uuid|marked|@wordpress/theme|@wordpress/ui)/)',
	],
};
