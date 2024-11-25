import { __ } from '@wordpress/i18n';
import { InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';

const TEMPLATE = [
	['core/image', { alt: '{{name}}', className: 'rsvp-avatar' }],
	['core/paragraph', { content: '{{name}}', className: 'rsvp-name' }],
];

const Edit = ({ attributes, setAttributes }) => {
	return (
	<>
		<div>
			<InnerBlocks template={TEMPLATE} templateLock="all" />
		</div>
	</>
	);
};

export default Edit;
