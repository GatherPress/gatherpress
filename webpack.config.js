/**
 * External Dependencies
 */
const fs = require('fs');
const path = require('path');

/**
 * WordPress Dependencies
 */
const defaultConfig = require('@wordpress/scripts/config/webpack.config.js');

/**
 * Retrieves the entry points for variation JavaScript files located in the
 * 'src/variations' directory.
 *
 * This function checks if the 'variations' directory exists in the current
 * working directory. If it does, it reads the subdirectories within it and
 * maps each variation's `index.js` file to an entry point, where the key
 * is in the format `variations/{variation}/index` and the value is the
 * path to the `index.js` file.
 *
 * @return {Object} An object where each key is a variation entry path
 *                  (e.g., `variations/{variation}/index`) and each value
 *                  is the corresponding path to the `index.js` file for
 *                  that variation. Returns an empty object if the
 *                  'variations' directory does not exist.
 */
function getVariationEntries() {
	const variationsDir = path.resolve(process.cwd(), 'src', 'variations');
	const entries = {};

	if (!fs.existsSync(variationsDir)) {
		return entries;
	}

	const variationDirs = fs.readdirSync(variationsDir);

	for (const variation of variationDirs) {
		const variationPath = path.join(variationsDir, variation);
		entries[`variations/${variation}/index`] = path.join(
			variationPath,
			'index.js'
		);
	}

	return entries;
}

module.exports = [
	...defaultConfig,
	{
		...defaultConfig[0],
		entry: {
			...defaultConfig[0].entry(),
			admin_style: path.resolve(process.cwd(), 'src', 'admin.scss'),
			editor: path.resolve(process.cwd(), 'src', 'editor.js'),
			panels: path.resolve(process.cwd(), 'src/panels', 'index.js'),
			modals: path.resolve(process.cwd(), 'src/modals', 'index.js'),
			settings: path.resolve(process.cwd(), 'src/settings', 'index.js'),
			settings_style: path.resolve(
				process.cwd(),
				'src/settings',
				'style.scss'
			),
			profile: path.resolve(process.cwd(), 'src/profile', 'index.js'),
			profile_style: path.resolve(
				process.cwd(),
				'src/profile',
				'style.scss'
			),
			utility_style: path.resolve(process.cwd(), 'src', 'utility.scss'),
			...getVariationEntries(),
		},
		module: {
			...defaultConfig[0].module,
			rules: [
				...defaultConfig[0].module.rules.filter(
					(rule) =>
						!/\\\.\(bmp\|png\|jpe\?g\|gif\|webp\)\$/i.test(
							rule.test.toString()
						)
				),
				...[
					{
						test: /\.(bmp|png|jpe?g|gif|webp)$/i,
						type: 'asset/resource',
						generator: {
							filename: 'images/[name][ext]',
						},
					},
				],
			],
		},
	},
];
