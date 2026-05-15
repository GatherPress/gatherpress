/**
 * WordPress dependencies
 */
import { store as blockEditorStore } from '@wordpress/block-editor';
import {
	createBlock,
	getBlockType,
	serialize,
} from '@wordpress/blocks';
import { dispatch, select } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import EVENT_CARD_WITH_RSVP_TEMPLATE from './templates/event-card-with-rsvp';

/**
 * Default `core/query` attributes for the Event Query Loop variation. Mirrors
 * QUERY_ATTRIBUTES in `../index.js` so picking a starter pattern keeps the
 * variation's `isActive` match (`namespace` + `query.postType`).
 */
const QUERY_ATTRIBUTES = {
	namespace: 'gatherpress-event-query',
	query: {
		perPage: 5,
		pages: 0,
		offset: 0,
		postType: 'gatherpress_event',
		gatherpress_event_query: 'upcoming',
		include_unfinished: 1,
		order: 'asc',
		orderBy: 'datetime',
		inherit: false,
	},
	className: 'gatherpress-event-query',
};

/**
 * Recursively converts a `[ name, attrs, inner ]` template tuple into a tree
 * of `wp.blocks` block instances ready for `serialize()`.
 *
 * @param {Array} template Array of `[ block_name, attributes, inner_blocks ]` tuples.
 * @return {Array} Created block instances.
 */
function templateToBlocks( template ) {
	return template.map( ( [ name, attributes, innerBlocks ] ) =>
		createBlock(
			name,
			attributes,
			templateToBlocks( innerBlocks || [] )
		)
	);
}

/**
 * Builds a starter pattern for the chooser modal. Wraps the inner template in
 * a `core/query` block with the variation's default attributes, then serializes
 * the whole tree — `serialize()` walks each block's registered `save()` so
 * static blocks (group, columns, media-text) include their wrapping HTML and
 * the editor doesn't flag the inserted content as malformed.
 *
 * @param {Object} props
 * @param {string} props.name        Pattern slug, e.g. `gatherpress/event-card-with-rsvp`.
 * @param {string} props.title       Human-readable title shown in the modal.
 * @param {string} props.description Sentence summary shown alongside the preview.
 * @param {Array}  props.template    Inner template tuples (post-template + pagination + no-results).
 * @return {Object} Pattern descriptor in the shape `__experimentalBlockPatterns` accepts.
 */
function buildPattern( { name, title, description, template } ) {
	const queryBlock = createBlock(
		'core/query',
		QUERY_ATTRIBUTES,
		templateToBlocks( template )
	);

	return {
		name,
		title,
		description,
		// Scoping to `core/query/<namespace>` (instead of just `core/query`)
		// hides this pattern from regular Query Loop pickers and makes the
		// Event Query Loop variation's chooser show ONLY our patterns
		// — core's generic post patterns drop out of that modal.
		blockTypes: [ 'core/query/gatherpress-event-query' ],
		categories: [ 'gatherpress-event-query' ],
		content: serialize( [ queryBlock ] ),
	};
}

/**
 * Inject the Event Query Loop starter patterns into the editor settings.
 *
 * Wrapped in `domReady` so every block's save function is registered before
 * `serialize()` runs — patterns built earlier would emit empty inner HTML for
 * static blocks and trip the editor's block-validation warnings on insert.
 */
// Block names that must be registered before we serialize — `createBlock`
// recurses on unregistered blocks (it returns the same shape the parent
// passes back into itself) and stack-overflows. Real editor sessions register
// every gatherpress + core block we touch, but the variation module is also
// imported by jest tests where most blocks are absent. The early-exit makes
// patterns a no-op outside the editor without forcing every test to mock us.
const REQUIRED_BLOCKS = [
	'core/query',
	'core/post-template',
	'core/query-pagination',
	'core/query-no-results',
	'gatherpress/event-date',
];

const allBlocksRegistered = () =>
	REQUIRED_BLOCKS.every( ( name ) => !! getBlockType( name ) );

domReady( () => {
	if ( ! allBlocksRegistered() ) {
		return;
	}

	const patterns = [
		buildPattern( {
			name: 'gatherpress/event-card-with-rsvp',
			title: __( 'Event Card with RSVP', 'gatherpress' ),
			description: __(
				'Featured image, date, title, venue, online event link, RSVP responses, and RSVP button.',
				'gatherpress'
			),
			template: EVENT_CARD_WITH_RSVP_TEMPLATE,
		} ),
	];

	// The editor's own settings push (with theme/REST patterns) can land after
	// our domReady fire and clobber our injection. Subscribe permanently and
	// re-inject whenever our pattern goes missing — the dispatch is a no-op
	// when the pattern is already present, so steady-state is one check
	// per store change.
	const inject = () => {
		const current =
			select( blockEditorStore ).getSettings()
				?.__experimentalBlockPatterns || [];
		const presentNames = new Set( current.map( ( p ) => p.name ) );
		const missing = patterns.filter(
			( p ) => ! presentNames.has( p.name )
		);

		if ( missing.length ) {
			dispatch( blockEditorStore ).updateSettings( {
				__experimentalBlockPatterns: [ ...current, ...missing ],
			} );
		}
	};

	inject();
	wp.data.subscribe( inject );
} );
