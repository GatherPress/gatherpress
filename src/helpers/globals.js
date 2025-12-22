/**
 * Get a value from the global GatherPress object based on the provided dot-separated path.
 *
 * This function is designed to retrieve values from the global GatherPress object.
 * It takes a dot-separated path as an argument and traverses the object to return the specified value.
 * If the object or any level along the path is undefined, it returns undefined.
 *
 * @since 1.0.0
 *
 * @param {string} args - Dot-separated path to the desired property in the GatherPress global object.
 * @return {*} The value at the specified path in the GatherPress global object or undefined if not found.
 */
export function getFromGlobal( args ) {
	// eslint-disable-next-line no-undef
	if ( 'object' !== typeof GatherPress ) {
		return undefined;
	}

	return args.split( '.' ).reduce(
		// eslint-disable-next-line no-undef
		( GatherPress, level ) => GatherPress && GatherPress[ level ],
		// eslint-disable-next-line no-undef
		GatherPress,
	);
}

/**
 * Set a value to a global object based on the provided path.
 *
 * This function allows setting values within a nested global object using a dot-separated path.
 * If the global object (GatherPress) does not exist, it will be initialized.
 *
 * @since 1.0.0
 *
 * @param {string} args  - Dot-separated path to the property.
 * @param {*}      value - The value to set.
 *
 * @return {void}
 */
export function setToGlobal( args, value ) {
	// eslint-disable-next-line no-undef
	if ( 'object' !== typeof GatherPress ) {
		return;
	}
	const properties = args.split( '.' );
	const last = properties.pop();

	// eslint-disable-next-line no-undef
	properties.reduce( ( all, item ) => ( all[ item ] ??= {} ), GatherPress )[ last ] =
		value;
}

/**
 * Strip <script> tags and "on*" attributes from HTML to sanitize it.
 *
 * This function removes <script> elements and any attributes starting with "on" (e.g., event handlers)
 * to mitigate potential XSS vulnerabilities. It is a similar implementation to WordPress Core's `safeHTML` function
 * in `dom.js`, tailored for use when the Core implementation is unavailable or unnecessary.
 *
 * @since 1.0.0
 *
 * @param {string} html - The raw HTML string to sanitize.
 *
 * @return {string} The sanitized HTML string.
 */
export function safeHTML( html ) {
	const { body } = document.implementation.createHTMLDocument( '' );
	body.innerHTML = html;
	const elements = body.getElementsByTagName( '*' );
	let elementIndex = elements.length;

	while ( elementIndex-- ) {
		const element = elements[ elementIndex ];
		if ( 'SCRIPT' === element.tagName ) {
			if ( element.parentNode ) {
				element.parentNode.removeChild( element );
			}
		} else {
			let attributeIndex = element.attributes.length;
			while ( attributeIndex-- ) {
				const { name: key } = element.attributes[ attributeIndex ];
				if ( key.startsWith( 'on' ) ) {
					element.removeAttribute( key );
				}
			}
		}
	}

	return body.innerHTML;
}

/**
 * Converts a snake_case string to camelCase.
 *
 * This function transforms a string in snake_case format into camelCase format by
 * removing underscores and capitalizing the first letter of each subsequent word.
 *
 * @since 1.0.0
 *
 * @param {string} snakeCaseString The snake_case string to be converted.
 * @return {string} The converted string in camelCase format.
 *
 * @example
 * // Converts "not_attending" to "notAttending"
 * const camelCaseString = toCamelCase("not_attending");
 * console.log(camelCaseString); // Outputs: "notAttending"
 */
export function toCamelCase( snakeCaseString ) {
	// First replace consecutive underscores with a single one.
	const normalized = snakeCaseString.replace( /__+/g, '_' );

	// Then do the camelCase conversion with a simpler regex.
	return normalized.replace( /_([a-zA-Z])/g, ( _, letter ) =>
		letter.toUpperCase(),
	);
}

/**
 * Get a URL parameter value by name.
 *
 * @since 1.0.0
 *
 * @param {string} name The parameter name to retrieve.
 * @return {string|null} The parameter value or null if not found.
 */
export function getUrlParam( name ) {
	const urlParams = new URLSearchParams( location.search );

	return urlParams.get( name );
}
