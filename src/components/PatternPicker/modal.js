/**
 * WordPress dependencies.
 */
import { BlockPreview } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { Modal } from '@wordpress/components';
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Recursively turn an InnerBlocks-shape `[ name, attrs, inner ]` tuple tree
 * into instantiated block objects that `<BlockPreview>` can render.
 *
 * @param {Array} template Tuples in `[ blockName, attributes, innerBlocks ]` form.
 * @return {Array} Created block instances.
 */
function templateToBlocks( template ) {
	return template.map( ( [ name, attributes, innerBlocks ] ) =>
		createBlock(
			name,
			attributes || {},
			templateToBlocks( innerBlocks || [] )
		)
	);
}

/**
 * The pattern chooser `<Modal>` — a grid of preview cards (rendered via
 * `<BlockPreview>`) with the pattern title underneath. Clicking a card calls
 * `onPick` and closes the modal. Used both by the placeholder Choose button
 * and the block toolbar's Choose pattern button.
 *
 * @param {Object}   props
 * @param {Array}    props.patterns Patterns to render — see `<PatternPicker>`.
 * @param {string}   [props.title]  Modal heading. Defaults to "Choose a pattern".
 * @param {Function} props.onPick   Called with the picked pattern object.
 * @param {Function} props.onClose  Called when the modal is dismissed without a pick.
 * @return {JSX.Element} The modal UI.
 */
const PatternChooserModal = ( { patterns, title, onPick, onClose } ) => {
	// Memoize the instantiated preview blocks so reopening doesn't rebuild
	// (potentially expensive) trees on every modal render.
	const patternsWithPreviewBlocks = useMemo(
		() =>
			patterns.map( ( pattern ) => ( {
				...pattern,
				previewBlocks: templateToBlocks( pattern.template ),
			} ) ),
		[ patterns ]
	);

	const handlePick = ( pattern ) => {
		onClose();
		onPick( pattern );
	};

	return (
		<Modal
			className="gatherpress-pattern-picker__modal"
			title={ title || __( 'Choose a pattern', 'gatherpress' ) }
			onRequestClose={ onClose }
			isFullScreen
		>
			<ul
				className="gatherpress-pattern-picker__patterns"
				role="listbox"
			>
				{ patternsWithPreviewBlocks.map( ( pattern ) => (
					<li
						key={ pattern.name }
						className="gatherpress-pattern-picker__pattern"
					>
						<button
							type="button"
							className="gatherpress-pattern-picker__pattern-button"
							onClick={ () => handlePick( pattern ) }
							aria-label={ pattern.title }
						>
							<div className="gatherpress-pattern-picker__pattern-preview">
								<BlockPreview
									blocks={ pattern.previewBlocks }
									viewportWidth={ 1200 }
								/>
							</div>
							<span className="gatherpress-pattern-picker__pattern-title">
								{ pattern.title }
							</span>
						</button>
					</li>
				) ) }
			</ul>
		</Modal>
	);
};

export default PatternChooserModal;
