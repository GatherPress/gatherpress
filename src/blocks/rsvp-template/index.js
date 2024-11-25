import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';

registerBlockType('gatherpress/rsvp-template', {
	edit: Edit,
	save: () => {
		return <InnerBlocks.Content />;
	},
});
