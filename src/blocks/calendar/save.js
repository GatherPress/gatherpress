/**
 * GatherPress Calendar Block Save Component
 *
 * Server-side rendering injects context for calendar-weeks, -days and -entries;
 * this save component preserves the InnerBlocks template structure.
 *
 * @since 0.4.0
 *
 * @return {Element} The React element representing the saved block content.
 */

import { InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return <InnerBlocks.Content />;
}
