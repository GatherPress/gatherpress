module.exports = {
	purge: {
		enable: true,
		content: [
			'../templates/**/*.php',
			'./src/**/*.js'
		]
	},
	theme: {
		extend: {}
	},
	variants: {
		display: [ 'group-hover' ]
	},
	plugins: [
		require( 'tailwindcss' ),
		require( 'autoprefixer' )
	]
};
