module.exports = {
	purge: {
		enable: true,
		content: [
			'../template-parts/*.php',
			'./src/js/event/attendance/*.js'
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
