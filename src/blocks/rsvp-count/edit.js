/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../helpers/globals';
import { hasValidEventId, DISABLED_FIELD_OPACITY, getEventMeta, isEventPostType } from '../../helpers/event';
import { isInFSETemplate } from '../../helpers/editor';

/**
 * Fetch RSVP responses from the API.
 *
 * @param {number} postId The post ID for which to fetch RSVP responses.
 * @return {Promise<Object>} The RSVP responses data.
 */
async function fetchRsvpResponses( postId ) {
	const apiUrl = getFromGlobal( 'urls.eventApiUrl' );
	const response = await fetch( `${ apiUrl }/rsvp-responses?post_id=${ postId }` );

	return response.json();
}

/**
 * Edit component for the GatherPress RSVP Count block.
 *
 * Renders a count display with configurable status type and labels.
 *
 * @param {Object}   root0               - The props object.
 * @param {Object}   root0.attributes    - Block attributes.
 * @param {Function} root0.setAttributes - Function to update block attributes.
 * @param {Object}   root0.context       - Block context data.
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered edit interface.
 */
const Edit = ( { attributes, setAttributes, context } ) => {
	const { status, singularLabel, pluralLabel } = attributes;

	const [ responses, setResponses ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	// Check if we're inside a query loop.
	const isDescendentOfQueryLoop = Number.isFinite( context?.queryId );

	// Check if context post type is an event.
	const isEventContext = isEventPostType( context?.postType );

	// Only use postId if context is an event or have an explicit override.
	const postId =
		( attributes?.postId || null ) ??
		( isEventContext ? context?.postId : null ) ??
		null;

	// Subscribe to event data to trigger re-renders when data loads.
	// This ensures hasValidEventId works correctly after async data fetch.
	// Only subscribe if we have a valid postId from event context.
	useSelect(
		( select ) => {
			if ( postId && ( isDescendentOfQueryLoop || isEventContext ) ) {
				return getEventMeta( select, postId, attributes );
			}
			return null;
		},
		[ postId, attributes, isDescendentOfQueryLoop, isEventContext ]
	);

	// Check if block has a valid event connection.
	// Only check if we're in an event context and pass postType to avoid wrong API calls.
	const isValidEvent =
		( isDescendentOfQueryLoop || isEventContext ) &&
		hasValidEventId( postId, context?.postType );

	const blockProps = useBlockProps( {
		style: {
			opacity: ( isInFSETemplate() || isValidEvent ) ? 1 : DISABLED_FIELD_OPACITY,
		},
	} );

	// Fetch responses when postId changes.
	useEffect( () => {
		// Only fetch if we have a postId from event context.
		if ( ! postId || ( ! isDescendentOfQueryLoop && ! isEventContext ) ) {
			setResponses( null );
			setLoading( false );
			return;
		}

		setLoading( true );

		fetchRsvpResponses( postId )
			.then( ( response ) => {
				setResponses( response.data );
				setLoading( false );
			} )
			.catch( () => {
				setLoading( false );
			} );
	}, [ postId, isDescendentOfQueryLoop, isEventContext ] );

	// Map status to response key.
	const statusMap = {
		attending: 'attending',
		waiting_list: 'waiting_list',
		not_attending: 'not_attending',
	};

	// Get count from responses.
	const responseKey = statusMap[ status ] || 'attending';
	const count = responses?.[ responseKey ]?.count ?? 0;

	// Format display text.
	const displayLabel = 1 === count ? singularLabel : pluralLabel;
	const displayText = displayLabel.replace( '%d', count );

	const statusOptions = [
		{ label: __( 'Attending', 'gatherpress' ), value: 'attending' },
		{
			label: __( 'Waiting List', 'gatherpress' ),
			value: 'waiting_list',
		},
		{
			label: __( 'Not Attending', 'gatherpress' ),
			value: 'not_attending',
		},
	];

	if ( loading ) {
		return (
			<div { ...blockProps }>
				<Spinner />
			</div>
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'RSVP Count Settings', 'gatherpress' ) }
				>
					<SelectControl
						label={ __( 'RSVP Status', 'gatherpress' ) }
						value={ status }
						options={ statusOptions }
						onChange={ ( value ) =>
							setAttributes( { status: value } )
						}
						help={ __(
							'Select which RSVP status to display the count for.',
							'gatherpress',
						) }
					/>
					<TextControl
						label={ __( 'Singular Label', 'gatherpress' ) }
						value={ singularLabel }
						onChange={ ( value ) =>
							setAttributes( { singularLabel: value } )
						}
						// translators: %d is the placeholder text to be used in the label.
						help={ __(
							'Use %d as a placeholder for the count.',
							'gatherpress',
						) }
					/>
					<TextControl
						label={ __( 'Plural Label', 'gatherpress' ) }
						value={ pluralLabel }
						onChange={ ( value ) =>
							setAttributes( { pluralLabel: value } )
						}
						// translators: %d is the placeholder text to be used in the label.
						help={ __(
							'Use %d as a placeholder for the count.',
							'gatherpress',
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<span className="gatherpress-rsvp-count__text">
					{ displayText }
				</span>
			</div>
		</>
	);
};

export default Edit;
