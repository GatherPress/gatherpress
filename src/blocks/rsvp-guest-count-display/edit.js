/**
 * WordPress dependencies.
 */
import { _n, sprintf } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../helpers/globals';

/**
 * Edit function for the Guest Count Display Block.
 *
 * This function defines the edit interface for the Guest Count Display Block,
 * rendering the block's UI within the editor.
 *
 * @param {Object} root0         - The root properties object.
 * @param {Object} root0.context - The block's context, providing dynamic data.
 * @return {JSX.Element} The rendered edit interface for the block.
 */
const Edit = ({ context }) => {
	const blockProps = useBlockProps();
	const { commentId } = context;

	// Example guest count.
	let guestCount = 1;

	if (commentId) {
		const responses = getFromGlobal(
			'eventDetails.responses.attending.responses'
		);
		const matchedResponse = responses.find(
			(response) => response.commentId === commentId
		);

		if (matchedResponse) {
			guestCount = matchedResponse.guests;
		}
	}

	// If the guest count is 0, return nothing.
	if (0 === guestCount) {
		return <div {...blockProps}></div>;
	}

	const guestText = sprintf(
		/* translators: %d: Number of guests. Singular and plural forms are used for 1 guest and multiple guests, respectively. */
		_n('+%d guest', '+%d guests', guestCount, 'gatherpress'),
		guestCount
	);

	return <div {...blockProps}>{guestText}</div>;
};

export default Edit;
