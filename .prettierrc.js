const defaultConfig = require( '@wordpress/prettier-config' );

module.exports = {
	...defaultConfig,
	// Override with WordPress spacing standards that Prettier can handle
	bracketSpacing: true, // { foo } instead of {foo}
};