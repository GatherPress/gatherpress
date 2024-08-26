/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';

/**
 * UrlRewritePreview component for GatherPress.
 *
 * This component renders a preview of the rewritten url based on the specified string.
 * It listens for the 'input' event on the input field with the specified name and updates
 * the state with the new rewritten url.
 *
 * @since 1.0.0
 *
 * @param {Object} props             - Component props.
 * @param {Object} props.attrs       - Component attributes.
 * @param {string} props.attrs.name  - The name of the input field.
 * @param {string} props.attrs.value - The initial value of the input field (rewritten url).
 *
 * @return {JSX.Element} The rendered React component.
 */
const UrlRewritePreview = (props) => {
	const { name, value, suffix } = props.attrs;
	const [rewrittenUrlPart, setRewrittenUrlPart] = useState(value);

	const input = document.querySelector(`[name="${name}"]`);

	input.addEventListener(
		'input',
		(e) => {
			setRewrittenUrlPart(e.target.value);
		},
		{ once: true }
	);

	return (
		<>
			{window.GatherPress.urls.siteUrl + '/'}
			<strong>{rewrittenUrlPart}</strong>
			{'/' + suffix}
		</>
	);
};

export default UrlRewritePreview;
