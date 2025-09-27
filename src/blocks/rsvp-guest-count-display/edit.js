/**
 * WordPress dependencies.
 */
import { _n, sprintf } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

/**
 * Edit function for the RSVP Guest Count Display Block.
 *
 * This function defines the edit interface for the RSVP Guest Count Display Block,
 * rendering the block's UI within the editor. It utilizes block context for dynamic data.
 *
 * @since 1.0.0
 *
 * @param {Object} root0         - The root properties object.
 * @param {Object} root0.context - The block's context, providing dynamic data.
 *
 * @return {JSX.Element} The rendered edit interface for the block.
 */
const Edit = ( { context } ) => {
	const { commentId } = context;
	const rsvpResponses = context?.[ 'gatherpress/rsvpResponses' ] ?? null;

	// Example guest count.
	let guestCount = 1;

	if ( commentId && rsvpResponses ) {
		const matchedResponse = rsvpResponses.attending.records.find(
			( response ) => response.commentId === commentId,
		);

		if ( matchedResponse ) {
			guestCount = matchedResponse.guests;
		}
	}

	// Get max attendance limit from meta.
	const maxAttendanceLimit = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'meta' )
				?.gatherpress_max_guest_limit,
		[],
	);

	// Add the `gatherpress--is-hidden` class conditionally via `useBlockProps`.
	const blockProps = useBlockProps( {
		className:
			0 === maxAttendanceLimit && ! commentId
				? 'gatherpress--is-hidden'
				: '',
	} );

	// If the guest count is 0, return nothing.
	if ( 0 === guestCount ) {
		return <div { ...blockProps }></div>;
	}

	const guestText = sprintf(
		/* translators: %d: Number of guests. Singular and plural forms are used for 1 guest and multiple guests, respectively. */
		_n( '+%d guest', '+%d guests', guestCount, 'gatherpress' ),
		guestCount,
	);

	return <div { ...blockProps }>{ guestText }</div>;
};

export default Edit;
