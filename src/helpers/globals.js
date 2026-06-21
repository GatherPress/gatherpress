/**
 * Strip `<script>` elements and `on*` event-handler attributes from an
 * HTML string. **Not a general-purpose HTML sanitizer.**
 *
 * What it removes:
 *
 * - Every `<script>` element.
 * - Every attribute whose name starts with `on` (e.g. `onclick`,
 *   `onload`, `onerror`).
 *
 * What it does **not** remove (every one of these is a real XSS vector):
 *
 * - `javascript:` URLs in `href` / `src` / `action` / etc.
 * - `data:` URIs with executable payloads (e.g. `data:text/html,...`).
 * - `<iframe>`, `<object>`, `<embed>`, `<form>`, `<style>` elements.
 * - `srcdoc`, `formaction`, `xlink:href`, `style` attributes.
 * - CSS `expression()` / `url(javascript:...)` in inline styles.
 *
 * Use this only as defense-in-depth on HTML that is already trusted
 * (server-rendered with proper escaping, REST responses produced by
 * GatherPress's own endpoints). Never feed raw user input through it
 * and treat the result as safe — it isn't. For untrusted HTML reach
 * for DOMPurify or equivalent.
 *
 * @since 0.27.0
 *
 * @param {string} html Raw HTML string.
 *
 * @return {string} The HTML with `<script>` elements and `on*` attributes removed.
 */
export function stripScriptsAndEventHandlers( html ) {
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
 * @since 0.27.0
 *
 * @param {string} snakeCaseString The snake_case string to be converted.
 *
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
 * @since 0.27.0
 *
 * @param {string} name The parameter name to retrieve.
 *
 * @return {string|null} The parameter value or null if not found.
 */
export function getUrlParam( name ) {
	const urlParams = new URLSearchParams( location.search );

	return urlParams.get( name );
}
