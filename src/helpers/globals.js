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
			element.remove();
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
	const normalized = snakeCaseString.replaceAll( /__+/g, '_' );

	// Then do the camelCase conversion with a simpler regex.
	return normalized.replaceAll( /_([a-zA-Z])/g, ( _, letter ) =>
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
