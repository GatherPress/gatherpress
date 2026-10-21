/**
 * GatherPress Calendar Entries Block Save Component
 *
 * Server-side rendering injects context per calendar-entry (event, production, post, ...);
 * this save component preserves the InnerBlocks template structure.
 *
 * @package
 * @since 0.4.0
 */
import { InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return <InnerBlocks.Content />;
}
