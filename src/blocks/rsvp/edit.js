/**
 * WordPress dependencies.
 */
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { createBlock, parse, serialize } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import TEMPLATES from './templates';
import { hasValidEventId, DISABLED_FIELD_OPACITY } from '../../helpers/event';
import { isInFSETemplate, getEditorDocument } from '../../helpers/editor';

/**
 * Helper function to convert a template to blocks.
 *
 * @param {Array} template The block template structure.
 * @return {Array} Array of blocks created from the template.
 */
function templateToBlocks( template ) {
	return template.map( ( [ name, attributes, innerBlocks ] ) => {
		return createBlock(
			name,
			attributes,
			templateToBlocks( innerBlocks || [] ),
		);
	} );
}

/**
 * Edit component for the GatherPress RSVP block.
 *
 * @param {Object}   props               The props passed to the component.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 * @param {string}   props.clientId      The unique ID of the block instance.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered edit interface for the RSVP block.
 */
const Edit = ( { attributes, setAttributes, clientId } ) => {
	const { serializedInnerBlocks = '{}', selectedStatus, postId } = attributes;
	const { replaceInnerBlocks } = useDispatch( blockEditorStore );

	// Check if block has a valid event connection.
	const isValidEvent = hasValidEventId( postId );

	const blockProps = useBlockProps( {
		style: {
			opacity: ( isInFSETemplate() || isValidEvent ) ? 1 : DISABLED_FIELD_OPACITY,
		},
	} );

	// Get the current inner blocks
	const innerBlocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ),
		[ clientId ],
	);

	// Get event data - either from override postId or current post.
	const { maxAttendanceLimit, enableAnonymousRsvp } = useSelect(
		( select ) => {
			let maxLimit;
			let enableAnonymous;

			// Check if we have a postId override.
			if ( postId ) {
				// Fetch from specific post via core data store.
				const post = select( 'core' ).getEntityRecord( 'postType', 'gatherpress_event', postId );
				maxLimit = post?.meta?.gatherpress_max_guest_limit;
				enableAnonymous = Boolean( post?.meta?.gatherpress_enable_anonymous_rsvp );
			} else {
				// Check if current post is an event.
				const currentPostType = select( 'core/editor' )?.getCurrentPostType();
				const isCurrentPostEvent = 'gatherpress_event' === currentPostType;

				if ( isCurrentPostEvent ) {
					const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
					maxLimit = meta?.gatherpress_max_guest_limit;
					enableAnonymous = Boolean( meta?.gatherpress_enable_anonymous_rsvp );
				}
			}

			return {
				maxAttendanceLimit: maxLimit,
				enableAnonymousRsvp: enableAnonymous,
			};
		},
		[ postId ]
	);

	/**
	 * Apply conditional visibility class to form fields based on event settings.
	 *
	 * @param {Array} blocks Array of blocks to process.
	 * @return {Array} Processed blocks with conditional classes applied.
	 */
	const applyFormFieldVisibility = useCallback( ( blocks ) => {
		return blocks.map( ( block ) => {
			// Check if this is a form-field block that needs conditional visibility.
			if ( 'gatherpress/form-field' === block.name ) {
				const fieldName = block.attributes?.fieldName;
				let shouldDisable = false;

				// Determine if the field should be disabled based on its field name.
				if ( 'gatherpress_rsvp_guests' === fieldName ) {
					shouldDisable = 0 === parseInt( maxAttendanceLimit, 10 );
				} else if ( 'gatherpress_rsvp_anonymous' === fieldName ) {
					// enableAnonymousRsvp is now a boolean from the useSelect conversion.
					shouldDisable = ! enableAnonymousRsvp;
				}

				// Only process fields that have conditional visibility.
				if ( 'gatherpress_rsvp_guests' === fieldName || 'gatherpress_rsvp_anonymous' === fieldName ) {
					const newAttributes = { ...block.attributes };

					if ( shouldDisable ) {
						newAttributes[ 'data-gatherpress-no-render' ] = 'true';
					} else {
						delete newAttributes[ 'data-gatherpress-no-render' ];
					}

					return {
						...block,
						attributes: newAttributes,
					};
				}
			}

			// Recursively process inner blocks.
			if ( block.innerBlocks && 0 < block.innerBlocks.length ) {
				return {
					...block,
					innerBlocks: applyFormFieldVisibility( block.innerBlocks ),
				};
			}

			return block;
		} );
	}, [ maxAttendanceLimit, enableAnonymousRsvp ] );

	// Save the provided inner blocks to the serializedInnerBlocks attribute
	const saveInnerBlocks = useCallback(
		( state, newState, blocks ) => {
			const currentSerializedBlocks = JSON.parse(
				serializedInnerBlocks || '{}',
			);

			// Encode the serialized content for safe use in HTML attributes
			const sanitizedSerialized = serialize( blocks );

			const updatedBlocks = {
				...currentSerializedBlocks,
				[ state ]: sanitizedSerialized,
			};

			delete updatedBlocks[ newState ];

			setAttributes( {
				serializedInnerBlocks: JSON.stringify( updatedBlocks ),
			} );
		},
		[ serializedInnerBlocks, setAttributes ],
	);

	// Load inner blocks for a given state
	const loadInnerBlocksForState = useCallback(
		( state ) => {
			const savedBlocks = JSON.parse( serializedInnerBlocks || '{}' )[
				state
			];
			if ( savedBlocks && 0 < savedBlocks.length ) {
				replaceInnerBlocks( clientId, parse( savedBlocks, {} ) );
			}
		},
		[ clientId, replaceInnerBlocks, serializedInnerBlocks ],
	);

	// Handle status change: save current inner blocks and load new ones
	const handleStatusChange = ( newStatus ) => {
		loadInnerBlocksForState( newStatus ); // Load blocks for the new state
		setAttributes( {
			selectedStatus: newStatus,
		} ); // Update the state
		saveInnerBlocks( selectedStatus, newStatus, innerBlocks ); // Save current inner blocks before switching state
	};

	// Hydrate inner blocks for all statuses if not set
	useEffect( () => {
		const hydrateInnerBlocks = () => {
			const currentSerializedBlocks = JSON.parse(
				serializedInnerBlocks || '{}',
			);

			const updatedBlocks = Object.keys( TEMPLATES ).reduce(
				( updatedSerializedBlocks, templateKey ) => {
					if ( currentSerializedBlocks[ templateKey ] ) {
						updatedSerializedBlocks[ templateKey ] =
							currentSerializedBlocks[ templateKey ];

						return updatedSerializedBlocks;
					}

					if ( templateKey !== selectedStatus ) {
						const blocks = templateToBlocks( TEMPLATES[ templateKey ] );

						updatedSerializedBlocks[ templateKey ] =
							serialize( blocks );
					}

					return updatedSerializedBlocks;
				},
				{ ...currentSerializedBlocks },
			);

			setAttributes( {
				serializedInnerBlocks: JSON.stringify( updatedBlocks ),
			} );
		};

		// Adding a setTimeout with 0ms delay pushes execution to the end of the event queue,
		// ensuring WordPress has properly initialized the post state before we attempt to
		// hydrate inner blocks. This prevents false "new post" detection that could interfere
		// with block initialization.
		setTimeout( () => {
			hydrateInnerBlocks();
		}, 0 );
	}, [ serializedInnerBlocks, setAttributes, selectedStatus ] );

	// Apply form field visibility via CSS when event settings change.
	useEffect( () => {
		const editorDoc = getEditorDocument();
		const styleId = `gatherpress-rsvp-visibility-${ clientId }`;
		let styleElement = editorDoc.getElementById( styleId );

		if ( ! styleElement ) {
			styleElement = editorDoc.createElement( 'style' );
			styleElement.id = styleId;
			editorDoc.head.appendChild( styleElement );
		}

		const styles = [];

		// Hide guest count field if max attendance limit is 0.
		if ( 0 === parseInt( maxAttendanceLimit, 10 ) ) {
			styles.push( `#block-${ clientId } .gatherpress-rsvp-field-guests { opacity: ${ DISABLED_FIELD_OPACITY }; pointer-events: none; }` );
		}

		// Hide anonymous field if anonymous RSVP is disabled.
		if ( ! enableAnonymousRsvp ) {
			styles.push( `#block-${ clientId } .gatherpress-rsvp-field-anonymous { opacity: ${ DISABLED_FIELD_OPACITY }; pointer-events: none; }` );
		}

		styleElement.textContent = styles.join( '\n' );

		// Cleanup on unmount.
		return () => {
			if ( styleElement && styleElement.parentNode ) {
				styleElement.parentNode.removeChild( styleElement );
			}
		};
	}, [ maxAttendanceLimit, enableAnonymousRsvp, clientId ] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'RSVP Block Settings', 'gatherpress' ) }>
					<p>
						{ __(
							'Select an RSVP status to edit how this block appears for users with that status.',
							'gatherpress',
						) }
					</p>
					<SelectControl
						label={ __( 'Edit Block Status', 'gatherpress' ) }
						value={ selectedStatus }
						options={ [
							{
								label: __(
									'No Response (Default)',
									'gatherpress',
								),
								value: 'no_status',
							},
							{
								label: __( 'Attending', 'gatherpress' ),
								value: 'attending',
							},
							{
								label: __( 'Waiting List', 'gatherpress' ),
								value: 'waiting_list',
							},
							{
								label: __( 'Not Attending', 'gatherpress' ),
								value: 'not_attending',
							},
							{
								label: __( 'Past Event', 'gatherpress' ),
								value: 'past',
							},
						] }
						onChange={ handleStatusChange }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<InnerBlocks template={ TEMPLATES[ selectedStatus ] } />
			</div>
		</>
	);
};

export default Edit;
