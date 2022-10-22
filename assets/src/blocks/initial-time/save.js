import { __ } from '@wordpress/i18n';

import { useBlockProps } from '@wordpress/block-editor';

import { FormatTheDate } from '../helper-functions';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function Save( { attributes } ) {
	const { beginTime } = attributes;
	return (
		<div { ...useBlockProps.save() }>
			<p>
				{beginTime ? FormatTheDate(beginTime) : __('Start date not set', 'gb-blocks')}
			</p>
		</div>
	);
}
