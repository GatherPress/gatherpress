/**
 * WordPress dependencies.
 */
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import RsvpResponseNavigationItem from './RsvpResponseNavigationItem';
import { Listener } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';

/**
 * RsvpResponseNavigation component for GatherPress.
 *
 * This component represents the navigation for different RSVP statuses. It includes a dropdown menu
 * to switch between RSVP statuses, displaying the count of responses for each status. The active RSVP
 * status is highlighted, and clicking on it toggles the dropdown menu. The component listens for
 * document clicks and keyboard events to close the dropdown when clicked outside or on the 'Escape' key.
 *
 * @since 1.0.0
 *
 * @param {Object}   props              - Component props.
 * @param {Array}    props.items        - An array of objects representing different RSVP statuses.
 * @param {string}   props.activeValue  - The currently active RSVP status value.
 * @param {Function} props.onTitleClick - Callback function triggered when a title is clicked.
 * @param {number}   props.defaultLimit - The default limit of responses to display.
 *
 * @return {JSX.Element} The rendered React component.
 */
const RsvpResponseNavigation = ( {
	items,
	activeValue,
	onTitleClick,
	defaultLimit,
} ) => {
	const defaultCount = {
		all: 0,
		attending: 0,
		not_attending: 0, // eslint-disable-line camelcase
		waiting_list: 0, // eslint-disable-line camelcase
	};

	const responses = getFromGlobal( 'eventDetails.responses' ) ?? {};

	for ( const [ key, value ] of Object.entries( responses ) ) {
		defaultCount[ key ] = value.count;
	}

	const [ rsvpCount, setRsvpCount ] = useState( defaultCount );
	const [ showNavigationDropdown, setShowNavigationDropdown ] = useState( false );
	const [ hideNavigationDropdown, setHideNavigationDropdown ] = useState( true );
	const Tag = hideNavigationDropdown ? `span` : `a`;

	Listener( { setRsvpCount }, getFromGlobal( 'eventDetails.postId' ) );

	let activeIndex = 0;

	const renderedItems = items.map( ( item, index ) => {
		const activeItem = item.value === activeValue;

		if ( activeItem ) {
			activeIndex = index;
		}

		return (
			<RsvpResponseNavigationItem
				key={ index }
				item={ item }
				count={ rsvpCount[ item.value ] }
				activeItem={ activeItem }
				onTitleClick={ onTitleClick }
				defaultLimit={ defaultLimit }
			/>
		);
	} );

	useEffect( () => {
		document.addEventListener( 'click', ( { target } ) => {
			if (
				! target.closest( '.gatherpress-rsvp-response__navigation-active' )
			) {
				setShowNavigationDropdown( false );
			}
		} );

		document.addEventListener( 'keydown', ( { key } ) => {
			if ( 'Escape' === key ) {
				setShowNavigationDropdown( false );
			}
		} );
	} );

	useEffect( () => {
		if ( 0 === rsvpCount.not_attending && 0 === rsvpCount.waiting_list ) {
			setHideNavigationDropdown( true );
		} else {
			setHideNavigationDropdown( false );
		}
	}, [ rsvpCount ] );

	const toggleNavigation = ( e ) => {
		e.preventDefault();

		setShowNavigationDropdown( ! showNavigationDropdown );
	};

	return (
		<div className="gatherpress-rsvp-response__navigation-wrapper">
			<div>
				{ /* eslint-disable-next-line jsx-a11y/anchor-is-valid */ }
				<Tag
					href="#"
					className="gatherpress-rsvp-response__navigation-active"
					onClick={ ( e ) => toggleNavigation( e ) }
				>
					{ items[ activeIndex ].title }
				</Tag>
				&nbsp;
				<span>({ rsvpCount[ activeValue ] })</span>
			</div>
			{ ! hideNavigationDropdown && showNavigationDropdown && (
				<nav className="gatherpress-rsvp-response__navigation">
					{ renderedItems }
				</nav>
			) }
		</div>
	);
};

export default RsvpResponseNavigation;
