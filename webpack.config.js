/**
 * External Dependencies
 */
const path = require('path');

/**
 * WordPress Dependencies
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config.js');

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry(),
		panels: path.resolve(process.cwd(), 'src/panels', 'index.js'),
		settings: path.resolve(process.cwd(), 'src/settings', 'index.js'),
		settings_style: path.resolve(
			process.cwd(),
			'src/settings',
			'style.scss'
		),
	},
};
