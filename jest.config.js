const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,
	moduleNameMapper: {
		'\\.(png|jpg|jpeg|gif|webp)$':
			'<rootDir>/test/unit/js/__mocks__/fileMock.js',
		'^@src/(.*)$': '<rootDir>/src/$1',
		...defaultConfig.moduleNameMapper,
	},
	// These ship ESM only, so Jest has to transform them rather than skip
	// node_modules wholesale. `marked` arrived with @wordpress/blocks 15.25.0,
	// which dropped showdown for it (GHSA-cr32-g25g-vxjj, GHSA-22g5-r2x5-97cx).
	transformIgnorePatterns: [
		'node_modules/(?!(?:parsel-js|uuid|marked)/)',
	],
};
