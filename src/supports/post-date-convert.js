/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { Button, PanelBody } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { switchToBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import { usePostTypeSupports } from '../helpers/event';

/**
 * Adds a "Convert to Event Date" button to the core/post-date inspector
 * sidebar when the current post type supports `gatherpress-event`.
 *
 * The button reuses the registered cross-block transform (see
 * `src/blocks/event-date/transforms.js`), so the attribute mapping stays in
 * one place. Clicking it replaces the selected post-date block with a
 * gatherpress/event-date block carrying the same date format and alignment.
 *
 * Gated on the reactive `usePostTypeSupports` hook so the button only renders
 * where the resulting block has somewhere meaningful to read its datetime
 * from — on non-event post types the button stays hidden rather than letting
 * the user create a permanently dim event-date block.
 */
const withPostDateConvertToEventDate = createHigherOrderComponent(
	( BlockEdit ) => {
		return ( props ) => {
			const { name, clientId, attributes } = props;

			const supportsEventDate = usePostTypeSupports(
				'gatherpress-event'
			);

			const { replaceBlocks } = useDispatch( 'core/block-editor' );

			if ( 'core/post-date' !== name || ! supportsEventDate ) {
				return <BlockEdit { ...props } />;
			}

			const onConvert = () => {
				const block = { name, attributes, innerBlocks: [] };
				const newBlocks = switchToBlockType(
					block,
					'gatherpress/event-date'
				);

				if ( newBlocks ) {
					replaceBlocks( clientId, newBlocks );
				}
			};

			return (
				<>
					<BlockEdit { ...props } />
					<InspectorControls>
						<PanelBody>
							<p className="gatherpress-convert-to-event-date__description">
								{ __(
									"Display an event's date and time.",
									'gatherpress'
								) }
							</p>
							<Button
								className="gatherpress-convert-to-event-date"
								variant="secondary"
								onClick={ onConvert }
							>
								{ __(
									'Convert to Event Date',
									'gatherpress'
								) }
							</Button>
						</PanelBody>
					</InspectorControls>
				</>
			);
		};
	},
	'withPostDateConvertToEventDate'
);

addFilter(
	'editor.BlockEdit',
	'gatherpress/post-date-convert-to-event-date',
	withPostDateConvertToEventDate
);
