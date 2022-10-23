const defaultConfig = require( './node_modules/@wordpress/scripts/config/webpack.config.js' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		panels: path.resolve( process.cwd(), 'src/panels', 'index.js' ),
	},
};
