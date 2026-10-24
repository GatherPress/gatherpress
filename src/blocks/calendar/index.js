/**
 * GatherPress Calendar Block Registration
 *
 * @package
 * @since TBD
 */

import { registerBlockType } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { select } from '@wordpress/data';
import {
	InnerBlocks,
	store as blockEditorStore,
	useBlockProps,
} from '@wordpress/block-editor';

import './style.scss';
import Edit from './edit';
import save from './save';
import metadata from './block.json';
import transforms from './transforms';
import './variation';
import './variations/pagination';
import './variations/bound-blocks';
import './bindings';

/**
 * Register the GatherPress Calendar block type
 */
registerBlockType( metadata.name, {
	edit: Edit,
	save,
	transforms,
	deprecated: [
		// GatherPress Calendar plugin 0.1.0 to 0.4.1 saved a wrapper around the template.
		// A deprecation does not inherit attributes or supports, and the wrapper's
		// style attribute comes from the spacing support, so both are set here.
		{
			apiVersion: metadata.apiVersion,
			attributes: metadata.attributes,
			supports: metadata.supports,
			save() {
				return (
					<div
						{ ...useBlockProps.save( {
							className: 'gatherpress-calendar-block',
						} ) }
					>
						<InnerBlocks.Content />
					</div>
				);
			},
		},
	],
} );

/**
 * Reduce UI of GatherPress core query controls.
 *
 * Removes controls, that are not needed by a calendar-view, those are espceially:
 * - Upcoming / Past
 * - include unfinished events
 * - Offset number
 * - Max. count of events to query
 *
 * @see https://github.com/GatherPress/gatherpress/blob/main/docs/developer/blocks/slot-fills/README.md#add-or-remove-ui-elements
 *
 * @since TBD
 */
addFilter(
	'gatherpress.eventQueryControls',
	'gatherpress/reduce-query-controls',
	( controls, { clientId } ) => {
		const hasCalendar = select( blockEditorStore )
			.getBlock( clientId )
			?.innerBlocks.some(
				( block ) => 'gatherpress/calendar' === block.name,
			);

		return hasCalendar
			? controls.filter(
				( { name } ) =>
					! [
						'listType',
						'includeUnfinished',
						'offset',
						'count',
					].includes( name ),
			)
			: controls;
	},
);
