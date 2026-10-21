/**
 * GatherPress Calendar Day Block Save Component
 *
 * Server-side rendering generates the <td> container and injects context;
 * this save component preserves the InnerBlocks template structure.
 *
 * @package
 * @since 0.4.0
 */

import { InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return <InnerBlocks.Content />;
}
