/**
 * WordPress dependencies
 */
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock, getBlockType } from '@wordpress/blocks';
import { dispatch, select, subscribe } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';

const NAMESPACE = 'gatherpress-event-query';

/**
 * Recognizes any of WP core's "Start blank" scoped-variation scaffolds.
 *
 * The QueryPlaceholder's "Start blank" button doesn't insert a single shape —
 * it opens a sub-picker of four scoped Query Loop variations (Title & Date,
 * Title & Excerpt, Title/Date/Excerpt, Image/Date/Title). All four drop in a
 * `core/post-template` whose children are core post blocks, never our
 * `gatherpress/event-date`. We treat "post-template present and no event-date
 * anywhere in its subtree" as the signal that the user just picked a WP
 * scaffold rather than our event pattern (which threads event-date through
 * its media-text wrapper).
 *
 * @param {Array} innerBlocks Inner blocks of a `core/query` block.
 * @return {boolean} True when the inner-block tree looks like a WP scoped-variation scaffold.
 */
function isWpStartBlankShape( innerBlocks ) {
	const postTemplate = innerBlocks.find(
		( block ) => 'core/post-template' === block.name
	);

	if ( ! postTemplate || 0 === postTemplate.innerBlocks.length ) {
		return false;
	}

	return ! treeContainsBlock(
		postTemplate.innerBlocks,
		'gatherpress/event-date'
	);
}

/**
 * Depth-first check for a block name anywhere in a block tree.
 *
 * @param {Array}  blocks    Block tree to search.
 * @param {string} blockName Name to look for, e.g. `gatherpress/event-date`.
 * @return {boolean} True if any node in the tree matches.
 */
function treeContainsBlock( blocks, blockName ) {
	for ( const block of blocks ) {
		if ( block.name === blockName ) {
			return true;
		}
		if (
			block.innerBlocks &&
			treeContainsBlock( block.innerBlocks, blockName )
		) {
			return true;
		}
	}

	return false;
}

/**
 * Walk the picked WP scaffold and convert any `core/post-date` block into a
 * `gatherpress/event-date` block, leaving every other block (title, excerpt,
 * image) untouched. Returns the rebuilt block tree.
 *
 * Driven this way the "Title & Excerpt" scaffold — which has no date — is
 * left alone, while "Title & Date" / "Title, Date & Excerpt" /
 * "Image, Date & Title" all swap out their `post-date` for an event date.
 *
 * @param {Array} blocks Inner blocks to rebuild.
 * @return {Array} Created block instances ready for `replaceInnerBlocks()`.
 */
function rebuildWithEventDate( blocks ) {
	return blocks.map( ( block ) => {
		if ( 'core/post-date' === block.name ) {
			return createBlock( 'gatherpress/event-date', {
				displayType: 'start',
				startDateFormat: ' D, M j, Y, g:i a ',
			} );
		}

		return createBlock(
			block.name,
			block.attributes,
			rebuildWithEventDate( block.innerBlocks || [] )
		);
	} );
}

/**
 * Swap WP core's hardcoded "Start blank" scaffold's `core/post-date` for our
 * `gatherpress/event-date`.
 *
 * The Query Loop block's placeholder modal exposes a "Start blank" button that
 * is bound to a private handler in `@wordpress/block-library` — there's no
 * filter to override its inner blocks. Instead, we subscribe to the block
 * editor store and post-swap: when an Event Query Loop block ends up
 * containing one of WP's scoped-variation scaffolds, walk the post-template
 * tree and replace any `post-date` with `event-date`. Pagination/no-results
 * siblings stay intact, and scaffolds without a date (Title & Excerpt) pass
 * through unchanged so we don't add a date the user didn't ask for.
 *
 * Per-`clientId` guarding via `swappedClientIds` keeps the swap one-shot per
 * block — a user who later edits back to the WP scaffold isn't fought with.
 */
domReady( () => {
	if (
		! getBlockType( 'core/post-template' ) ||
		! getBlockType( 'gatherpress/event-date' )
	) {
		return;
	}

	const swappedClientIds = new Set();

	subscribe( () => {
		const blocks = select( blockEditorStore ).getBlocks();
		const eventQueryBlocks = [];

		const walk = ( list ) => {
			for ( const block of list ) {
				if (
					'core/query' === block.name &&
					NAMESPACE === block.attributes?.namespace
				) {
					eventQueryBlocks.push( block );
				}
				walk( block.innerBlocks );
			}
		};

		walk( blocks );

		for ( const block of eventQueryBlocks ) {
			if ( swappedClientIds.has( block.clientId ) ) {
				continue;
			}

			if ( ! isWpStartBlankShape( block.innerBlocks ) ) {
				continue;
			}

			const postTemplate = block.innerBlocks.find(
				( child ) => 'core/post-template' === child.name
			);

			swappedClientIds.add( block.clientId );

			// If the picked scaffold has no `post-date` (Title & Excerpt),
			// rebuilding is a no-op shape-wise — skip the dispatch to avoid
			// a pointless undo entry.
			if ( ! treeContainsBlock( postTemplate.innerBlocks, 'core/post-date' ) ) {
				continue;
			}

			dispatch( blockEditorStore ).replaceInnerBlocks(
				postTemplate.clientId,
				rebuildWithEventDate( postTemplate.innerBlocks )
			);
		}
	} );
} );
