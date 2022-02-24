const defaultConfig = require( './node_modules/@wordpress/scripts/config/webpack.config.js' );
const path = require( 'path' );
const IgnoreEmitPlugin = require( 'ignore-emit-webpack-plugin' );

module.exports = {
	...defaultConfig,
	entry: {
		blocks_style: path.resolve( process.cwd(), 'src', 'blocks/style.scss' ),
		blocks_backend: path.resolve( process.cwd(), 'src/blocks', 'backend.js' ),
		blocks_frontend: path.resolve( process.cwd(), 'src/blocks', 'frontend.js' ),
		panels: path.resolve( process.cwd(), 'src/panels', 'index.js' ),

	},
	optimization: {
		...defaultConfig.optimization,
		splitChunks: {
			cacheGroups: {
				blocks_style: {
					name: 'blocks_style',
					test: /blocks_style\.(sc|sa|c)ss$/,
					chunks: 'all',
					enforce: true
				},
				default: false
			}
		}
	},
	plugins: [
		...defaultConfig.plugins,
		new IgnoreEmitPlugin([ 'blocks_style.js' ])
	]
};
