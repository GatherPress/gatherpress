/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import { registerPlugin } from '@wordpress/plugins';

/**
 * Internal dependencies
 */
// Address autocomplete in VenueNavigator shares venue-detail address + popover styles.
import '../venue-detail/editor.scss';
import VenueBlockPluginFill from './slotfill';
import Edit from './edit';
import metadata from './block.json';

/**
 * Register the GatherPress Venue block.
 *
 * This code registers the GatherPress Venue block in the WordPress block editor.
 * It uses the block metadata from the 'block.json' file and associates it with the
 * edit component for rendering in the editor. The 'save' function is set to keep all inner blocks.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
registerBlockType( metadata, {
	edit: Edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
} );

/*
 */
registerPlugin( 'venue-block-slot-fill', {
	render: VenueBlockPluginFill,
} );
