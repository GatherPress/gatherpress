/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Edit function for Guest Count Display Block.
 *
 * @return {JSX.Element} The rendered edit interface for the block.
 */
const Edit = () => {
	const blockProps = useBlockProps();

	// Example guest count.
	const guestCount = 1;

	/* translators: %d: Number of guests. */
	const guestText = sprintf(__('+%d guest', 'gatherpress'), guestCount);

	return <div {...blockProps}>{guestText}</div>;
};

export default Edit;
