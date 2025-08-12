/**
 * WordPress dependencies.
 */
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Broadcaster } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';

/**
 * RsvpResponseNavigationItem component for GatherPress.
 *
 * This component represents an individual navigation item for different RSVP statuses.
 * It includes a link or span based on whether the item is active, and displays the count
 * of responses for that status. Clicking on the item triggers the `onTitleClick` callback.
 * The component is used within the `RsvpResponseNavigation` component.
 *
 * @since 1.0.0
 *
 * @param {Object}   props                    - Component props.
 * @param {Object}   props.item               - An object representing an RSVP status with `title` and `value`.
 * @param {boolean}  [props.activeItem=false] - Indicates whether the item is currently active.
 * @param {number}   props.count              - The count of responses for the RSVP status.
 * @param {Function} props.onTitleClick       - Callback function triggered when a title is clicked.
 * @param {number}   props.defaultLimit       - The default limit of responses to display.
 *
 * @return {JSX.Element|null} The rendered React component or null if not active.
 */
const RsvpResponseNavigationItem = ( {
	item,
	activeItem = false,
	count,
	onTitleClick,
	defaultLimit,
} ) => {
	const { title, value } = item;
	const active = ! ( 0 === count && 'attending' !== value );
	const Tag = activeItem ? `span` : `a`;
	const postId = getFromGlobal( 'eventDetails.postId' );
	const rsvpSeeAllLink = count > defaultLimit;

	useEffect( () => {
		if ( activeItem ) {
			Broadcaster( { setRsvpSeeAllLink: rsvpSeeAllLink }, postId );
		}
	} );

	if ( active ) {
		return (
			<div className="gatherpress-rsvp-response__navigation-item">
				{ /* eslint-disable-next-line jsx-a11y/anchor-is-valid */ }
				<Tag
					className="gatherpress-rsvp-response__anchor"
					data-item={ value }
					data-toggle="tab"
					href="#"
					role="tab"
					aria-controls={ `#gatherpress-rsvp-${ value }` }
					onClick={ ( e ) => onTitleClick( e, value ) }
				>
					{ title }
				</Tag>
				<span className="gatherpress-rsvp-response__count">
					({ count })
				</span>
			</div>
		);
	}

	return '';
};

export default RsvpResponseNavigationItem;
