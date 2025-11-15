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
	const { serializedInnerBlocks = '{}', selectedStatus } = attributes;
	const blockProps = useBlockProps();
	const { replaceInnerBlocks } = useDispatch( blockEditorStore );

	// Get the current inner blocks
	const innerBlocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ),
		[ clientId ],
	);

	// Get max attendance limit from post meta to control guest count field visibility.
	const maxAttendanceLimit = useSelect(
		( select ) => {
			const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
			return meta?.gatherpress_max_guest_limit;
		},
	);

	// Get anonymous RSVP setting from post meta to control anonymous checkbox visibility.
	const enableAnonymousRsvp = useSelect(
		( select ) => {
			const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
			// Convert meta value to boolean.
			return Boolean( meta?.gatherpress_enable_anonymous_rsvp );
		},
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
				let disabledReason = '';

				// Determine if the field should be disabled based on its field name.
				if ( 'gatherpress_rsvp_guest_count' === fieldName ) {
					shouldDisable = 0 === parseInt( maxAttendanceLimit, 10 );
					disabledReason = 'Guest count is disabled when attendance limit is not set.';
				} else if ( 'gatherpress_rsvp_anonymous' === fieldName ) {
					// enableAnonymousRsvp is now a boolean from the useSelect conversion.
					shouldDisable = ! enableAnonymousRsvp;
					disabledReason = 'Anonymous RSVP is disabled in event settings.';
				}

				// Only process fields that have conditional visibility.
				if ( 'gatherpress_rsvp_guest_count' === fieldName || 'gatherpress_rsvp_anonymous' === fieldName ) {
					const currentClassName = block.attributes?.className || '';
					let classNames = currentClassName.split( ' ' ).filter( Boolean );
					const dimmedClasses = [ 'gatherpress--is-dimmed' ];
					const hasDimmedClasses = dimmedClasses.some( ( cls ) => classNames.includes( cls ) );

					const newAttributes = { ...block.attributes };

					if ( shouldDisable && ! hasDimmedClasses ) {
						classNames.push( ...dimmedClasses );
						newAttributes.title = disabledReason;
						newAttributes[ 'aria-label' ] = `${ block.attributes?.label || fieldName } (disabled): ${ disabledReason }`;
					} else if ( ! shouldDisable && hasDimmedClasses ) {
						classNames = classNames.filter( ( name ) => ! dimmedClasses.includes( name ) );
						delete newAttributes.title;
						delete newAttributes[ 'aria-label' ];
					}

					const newClassName = classNames.join( ' ' );

					return {
						...block,
						attributes: {
							...newAttributes,
							className: newClassName,
						},
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

	// Apply form field visibility when event settings change.
	useEffect( () => {
		if ( innerBlocks && 0 < innerBlocks.length ) {
			const updatedBlocks = applyFormFieldVisibility( innerBlocks );

			// Only update if there are actual changes.
			const hasChanges = JSON.stringify( updatedBlocks ) !== JSON.stringify( innerBlocks );
			if ( hasChanges ) {
				replaceInnerBlocks( clientId, updatedBlocks );
			}
		}
	}, [ maxAttendanceLimit, enableAnonymousRsvp, clientId, replaceInnerBlocks, applyFormFieldVisibility, innerBlocks ] );

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
