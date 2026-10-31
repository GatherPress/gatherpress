/**
 * Calendar Day Block Save Component
 *
 * Server-side rendering generates the <td> container and injects context;
 * this save component preserves the InnerBlocks template structure.
 *
 * @package
 * @since TBD
 */

import { InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return <InnerBlocks.Content />;
}
