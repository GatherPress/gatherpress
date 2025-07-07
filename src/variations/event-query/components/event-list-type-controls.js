/**
 * WordPress dependencies
 */
import { ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __, _x, sprintf } from '@wordpress/i18n';

/**
 * A component that lets you select whether to query for upcoming or past events.
 *
 * @param {*} props
 * @return {Element} EventListTypeControls
 */
export const EventListTypeControls = ( { attributes, setAttributes } ) => {
	const { query: { gatherpress_events_query: eventListType = 'upcoming'  } = {} } = attributes;

	const currentPost = useSelect( ( select ) => {
		return select( 'core/editor' ).getCurrentPost();
	}, [] );

	if ( ! currentPost ) {
		return <div>{ __( 'Loading…', 'gatherpress' ) }</div>;
	}

	return (
		<>
			{/* <h2> { __( 'Type of event list', 'gatherpress' ) }</h2> */}
			<ToggleControl
				label={ __( 'Upcoming or past events.', 'gatherpress' ) }
				help={ sprintf(
					/* translators: %s: 'upcoming' or 'past' */
					_x( "Currently shows %s events.", "'upcoming' or 'past'", 'gatherpress' ),
					eventListType
				) }
				checked={ 'upcoming' === eventListType }
				onChange={ ( value ) => {
					setAttributes( {
						query: {
							...attributes.query,
							gatherpress_events_query: value ? 'upcoming' : 'past',
						},
					} );
				} }
			/>
		</>
	);
};
