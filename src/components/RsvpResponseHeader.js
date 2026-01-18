/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import RsvpResponseNavigation from './RsvpResponseNavigation';
import { useState } from '@wordpress/element';
import { Listener } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';

/**
 * RsvpResponseHeader component for GatherPress.
 *
 * This component represents the header of the RSVP response section. It includes the navigation
 * for different RSVP statuses, a toggle to show/hide more responses, and an icon for visual indication.
 * The component allows users to toggle the number of responses displayed based on the configured limit.
 *
 * @since 1.0.0
 *
 * @param {Object}         props              - Component props.
 * @param {Array}          props.items        - An array of objects representing different RSVP statuses.
 * @param {string}         props.activeValue  - The currently active RSVP status value.
 * @param {Function}       props.onTitleClick - Callback function triggered when a title is clicked.
 * @param {number|boolean} props.rsvpLimit    - The current limit of responses to display or false for no limit.
 * @param {Function}       props.setRsvpLimit - Callback function to set the new RSVP response limit.
 * @param {number}         props.defaultLimit - The default limit of responses to display.
 *
 * @return {JSX.Element} The rendered React component.
 */
const RsvpResponseHeader = ( {
	items,
	activeValue,
	onTitleClick,
	rsvpLimit,
	setRsvpLimit,
	defaultLimit,
} ) => {
	const updateLimit = ( e ) => {
		e.preventDefault();

		if ( false === rsvpLimit ) {
			setRsvpLimit( defaultLimit );
		} else {
			setRsvpLimit( false );
		}
	};

	let loadListText;

	if ( false === rsvpLimit ) {
		loadListText = __( 'See fewer', 'gatherpress' );
	} else {
		loadListText = __( 'See all', 'gatherpress' );
	}

	let defaultRsvpSeeAllLink = false;
	const responses = getFromGlobal( 'eventDetails.responses' );

	if ( responses && responses[ activeValue ] ) {
		defaultRsvpSeeAllLink =
			( getFromGlobal( 'eventDetails.responses' )[ activeValue ].count ?? 0 ) >
			defaultLimit;
	}

	const [ rsvpSeeAllLink, setRsvpSeeAllLink ] = useState( defaultRsvpSeeAllLink );

	Listener( { setRsvpSeeAllLink }, getFromGlobal( 'eventDetails.postId' ) );

	return (
		<div className="gatherpress-rsvp-response__header">
			<div className="dashicons dashicons-groups"></div>
			<RsvpResponseNavigation
				items={ items }
				activeValue={ activeValue }
				onTitleClick={ onTitleClick }
				defaultLimit={ defaultLimit }
			/>
			{ rsvpSeeAllLink && (
				<div className="gatherpress-rsvp-response__see-all">
					{ /* eslint-disable-next-line jsx-a11y/anchor-is-valid */ }
					<a href="#" onClick={ ( e ) => updateLimit( e ) }>
						{ loadListText }
					</a>
				</div>
			) }
		</div>
	);
};

export default RsvpResponseHeader;
