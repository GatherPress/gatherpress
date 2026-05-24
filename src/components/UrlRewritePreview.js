/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';

/**
 * UrlRewritePreview component for GatherPress.
 *
 * This component renders a preview of the rewritten url based on the specified string.
 * It listens for the 'input' event on the input field with the specified name and updates
 * the state with the new rewritten url.
 *
 * @since 0.27.0
 *
 * @param {Object} props               - Component props.
 * @param {Object} props.attrs         - Component attributes.
 * @param {string} props.attrs.name    - The name of the input field.
 * @param {string} props.attrs.value   - The initial value of the input field (rewritten url).
 * @param {string} props.attrs.suffix  - Sample suffix appended after the rewrite slug.
 * @param {string} props.attrs.homeUrl - Site home URL injected by the PHP partial.
 *
 * @return {JSX.Element} The rendered React component.
 */
const UrlRewritePreview = ( props ) => {
	const { name, value, suffix, homeUrl } = props.attrs;
	const [ rewrittenUrlPart, setRewrittenUrlPart ] = useState( value );

	const input = document.querySelector( `[name="${ name }"]` );

	input.addEventListener(
		'input',
		( e ) => {
			setRewrittenUrlPart( e.target.value );
		},
		{ once: true },
	);

	return (
		<>
			{ homeUrl + '/' }
			<strong>{ rewrittenUrlPart }</strong>
			{ '/' + suffix }
		</>
	);
};

export default UrlRewritePreview;
