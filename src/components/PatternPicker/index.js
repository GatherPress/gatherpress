/**
 * WordPress dependencies
 */
import { Button, Placeholder } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import PatternChooserModal from './modal';

// Styles for both the placeholder and the modal live in `src/admin.scss` —
// the modal portals to `document.body`, outside the block editor iframe, so
// its styles need to load admin-wide rather than via a block stylesheet.

/**
 * @typedef {Object} Pattern
 * @property {string} name        Stable identifier for the pattern (used as the React key).
 * @property {string} title       Translated, human-readable title — shown beneath the preview.
 * @property {string} description Translated, sentence-length summary — surfaced for screen readers.
 * @property {Array}  template    `[ name, attrs, innerBlocks ]` tuple tree handed back to `onPick`.
 */

/**
 * Reusable starter pattern picker that mirrors `core/query`'s placeholder UX —
 * a `<Placeholder>` with a **Choose** button (and optionally **Start blank**),
 * plus a `<PatternChooserModal>` showing the available patterns as a grid of
 * preview cards. Clicking a card picks the pattern.
 *
 * Consumer-provided strings (`label`, `instructions`, pattern titles and
 * descriptions, `chooseLabel`, `startBlankLabel`, `modalTitle`) are expected
 * to already be translated via `__()` / `_x()` at the call site so message
 * extraction picks them up in the right context. The component only translates
 * its own button-label fallbacks.
 *
 * @param {Object}     props
 * @param {string}     [props.label]           Heading shown at the top of the placeholder.
 * @param {string|JSX} [props.icon]            Dashicon slug or SVG component shown beside the heading.
 * @param {string}     [props.instructions]    Sentence-length description shown beneath the heading.
 * @param {Pattern[]}  props.patterns          Patterns the user can pick from inside the modal.
 * @param {string}     [props.chooseLabel]     Override the "Choose" button label.
 * @param {string}     [props.startBlankLabel] Override the "Start blank" button label.
 * @param {string}     [props.modalTitle]      Override the modal heading.
 * @param {boolean}    [props.showStartBlank]  Whether to render the secondary Start blank button. Defaults to true.
 * @param {Function}   props.onPick            Called with the picked pattern object.
 * @param {Function}   [props.onStartBlank]    Called when the user clicks Start blank. Required when `showStartBlank` is true.
 *
 * @return {JSX.Element} The placeholder + (conditionally) modal UI.
 */
const PatternPicker = ( {
	label,
	icon,
	instructions,
	patterns,
	chooseLabel,
	startBlankLabel,
	modalTitle,
	showStartBlank = true,
	onPick,
	onStartBlank,
} ) => {
	const [ isModalOpen, setIsModalOpen ] = useState( false );

	return (
		<>
			<Placeholder
				className="gatherpress-pattern-picker"
				icon={ icon }
				label={ label }
				instructions={ instructions }
			>
				<Button
					variant="primary"
					onClick={ () => setIsModalOpen( true ) }
				>
					{ chooseLabel || __( 'Choose', 'gatherpress' ) }
				</Button>
				{ showStartBlank && (
					<Button variant="secondary" onClick={ onStartBlank }>
						{ startBlankLabel ||
							__( 'Start blank', 'gatherpress' ) }
					</Button>
				) }
			</Placeholder>
			{ isModalOpen && (
				<PatternChooserModal
					patterns={ patterns }
					title={ modalTitle }
					onPick={ onPick }
					onClose={ () => setIsModalOpen( false ) }
				/>
			) }
		</>
	);
};

export default PatternPicker;
export { PatternChooserModal };
