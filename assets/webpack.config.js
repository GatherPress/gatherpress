const defaultConfig = require( './node_modules/@wordpress/scripts/config/webpack.config.js' );
const path = require( 'path' );
const IgnoreEmitPlugin = require( 'ignore-emit-webpack-plugin' );

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve( process.cwd(), 'src', 'index.js' ),
		style: path.resolve( process.cwd(), 'src', 'style.scss' ),
		script: path.resolve( process.cwd(), 'src/js', 'index.js' ),
		editor: path.resolve( process.cwd(), 'src', 'editor.scss' ),
		admin: path.resolve( process.cwd(), 'src', 'admin.scss' )
	},
	optimization: {
		...defaultConfig.optimization,
		splitChunks: {
			cacheGroups: {
				admin: {
					name: 'admin',
					test: /admin\.(sc|sa|c)ss$/,
					chunks: 'all',
					enforce: true
				},
				editor: {
					name: 'editor',
					test: /editor\.(sc|sa|c)ss$/,
					chunks: 'all',
					enforce: true
				},
				style: {
					name: 'style',
					test: /style\.(sc|sa|c)ss$/,
					chunks: 'all',
					enforce: true
				},
				default: false
			}
		}
	},
	plugins: [
		...defaultConfig.plugins,
		new IgnoreEmitPlugin([ 'editor.js', 'style.js', 'admin.js' ])
	]
};
