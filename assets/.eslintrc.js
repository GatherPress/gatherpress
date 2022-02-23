module.exports = {
	'env': {
		'browser': true,
		'es2020': true
	},
	'extends': [
		'plugin:@wordpress/eslint-plugin/recommended'
	],
	'parserOptions': {
		'ecmaFeatures': {
			'jsx': true
		},
		'ecmaVersion': 12,
		'sourceType': 'module'
	},
	'plugins': [
		'react'
	],
	'rules': {
	}
};
