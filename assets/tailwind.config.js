module.exports = {
	// @todo figure out purge.
	purge: [],
	theme: {
		extend: {},
	},
	variants: {
		display: ['group-hover']
	},
	plugins: [
		require('tailwindcss'),
		require('autoprefixer'),
	],
}
