const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,
	moduleNameMapper: {
		'\\.(png|jpg|jpeg|gif|webp)$':
			'<rootDir>/test/unit/js/__mocks__/fileMock.js',
		...defaultConfig.moduleNameMapper,
	},
	transformIgnorePatterns: [
		'node_modules/(?!(?:parsel-js)/)',
	],
};
