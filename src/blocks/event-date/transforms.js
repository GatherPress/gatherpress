/**
 * Block transforms for `gatherpress/event-date`.
 *
 * Uses the standard Block Transforms API (`transforms.from`) to register a
 * one-way transform from `core/post-date` → `gatherpress/event-date`. This
 * is the canonical mechanism for cross-block transforms in WordPress and is
 * what core surfaces in the block toolbar's "Transform to" menu — no
 * custom toolbar buttons or inspector slot-fills required.
 *
 * The transform is gated to post types that declare
 * `gatherpress-event-date` support via `isMatch`; on a post type without
 * that support the resulting block has no datetime source to bind to, so
 * surfacing the option would just be a dead end. `isMatch` reads the
 * editor's current post type non-reactively — block-toolbar transforms are
 * evaluated per click, not per render, so a one-shot `select()` is fine.
 *
 * @since 0.34.0
 */

/**
 * WordPress dependencies
 */
import { createBlock } from '@wordpress/blocks';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { isPostTypeSupporting } from '../../helpers/event';

const transforms = {
	from: [
		{
			type: 'block',
			blocks: [ 'core/post-date' ],
			isMatch: () => {
				const postType = select( 'core/editor' )?.getCurrentPostType();

				if ( ! postType ) {
					return false;
				}

				return isPostTypeSupporting(
					'gatherpress-event-date',
					postType
				);
			},
			transform: ( { format = '' } ) =>
				createBlock( 'gatherpress/event-date', {
					// Map core/post-date's single `format` to both the
					// start and end date formats — the user explicitly
					// chose this format for "the post date," so it's the
					// most faithful default for "this event's date."
					startDateFormat: format,
					endDateFormat: format,
				} ),
		},
	],
};

export default transforms;
