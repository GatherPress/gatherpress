/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	BlockContextProvider,
	BlockControls,
	InnerBlocks,
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
	PanelBody,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
	Spinner,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import RsvpManager from './rsvp-manager';
import TEMPLATE from './template';
import { getFromGlobal } from '../../helpers/globals';
import { hasValidEventId, isEventPostType, DISABLED_FIELD_OPACITY } from '../../helpers/event';
import { getEditorDocument, isInFSETemplate } from '../../helpers/editor';

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
 * Edit component for the GatherPress RSVP Response block.
 *
 * This component handles the rendering and logic for the editor interface
 * of the GatherPress RSVP Response block. It fetches RSVP data, manages
 * block-specific state, and passes relevant context to child blocks.
 *
 * @param {Object}   root0               - The props object passed to the component.
 * @param {Object}   root0.attributes    - Block attributes containing configuration and data.
 * @param {Object}   root0.context       - Block context data containing postId and event info.
 * @param {Function} root0.setAttributes - Function to update block attributes.
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered edit interface for the block.
 */
const Edit = ( { attributes, setAttributes, context } ) => {
	const [ editMode, setEditMode ] = useState( false );
	const [ showEmptyRsvpBlock, setShowEmptyRsvpBlock ] = useState( false );
	const [ defaultStatus, setDefaultStatus ] = useState( 'attending' );
	const [ responses, setResponses ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const postId = attributes?.postId ?? context?.postId ?? null;
	const { rsvpLimitEnabled, rsvpLimit } = attributes;

	// Check if block has a valid event connection.
	const isValidEvent = hasValidEventId( postId );

	const blockProps = useBlockProps( {
		style: {
			opacity: ( isInFSETemplate() || isValidEvent ) ? 1 : DISABLED_FIELD_OPACITY,
		},
	} );

	useEffect( () => {
		const editorDoc = getEditorDocument();

		const updateBlockVisibility = () => {
			const emptyBlocks = editorDoc.querySelectorAll(
				'.gatherpress-rsvp-response--no-responses',
			);
			const responseBlocks = editorDoc.querySelectorAll(
				'.gatherpress--rsvp-responses',
			);

			emptyBlocks.forEach( ( block ) => {
				block.style.setProperty(
					'display',
					showEmptyRsvpBlock ? 'block' : 'none',
					'important',
				);
			} );
			responseBlocks.forEach( ( block ) => {
				if ( ! showEmptyRsvpBlock ) {
					block.style.removeProperty( 'display' );
				} else {
					block.style.setProperty( 'display', 'none', 'important' );
				}
			} );
		};

		// Watch for DOM changes.
		const observer = new MutationObserver( updateBlockVisibility );

		observer.observe( editorDoc.body, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: [ 'class' ],
		} );

		// Initial call.
		updateBlockVisibility();

		return () => observer.disconnect();
	}, [ showEmptyRsvpBlock, responses ] );

	// Fetch responses when postId changes.
	useEffect( () => {
		if ( ! postId ) {
			setResponses( null );
			setLoading( false );
			return;
		}

		setLoading( true );
		setError( null );

		fetchRsvpResponses( postId )
			.then( ( response ) => {
				setResponses( response.data );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message );
				setLoading( false );
			} );
	}, [ postId ] );

	const onEditClick = ( e ) => {
		e.preventDefault();
		setEditMode( ! editMode );
	};

	if ( loading ) {
		return (
			<div { ...blockProps }>
				<Spinner />
			</div>
		);
	}

	if ( error ) {
		return (
			<div { ...blockProps }>
				<p>{ __( 'Failed to load RSVP responses.', 'gatherpress' ) }</p>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			<BlockContextProvider
				value={ {
					'gatherpress/rsvpResponses': responses,
					'gatherpress/rsvpLimitEnabled': rsvpLimitEnabled,
					'gatherpress/rsvpLimit': rsvpLimit,
					postId,
				} }
			>
				<InspectorControls>
					<PanelBody>
						<ToggleControl
							label={ __( 'Show Empty RSVP Block', 'gatherpress' ) }
							checked={ showEmptyRsvpBlock }
							onChange={ ( value ) => setShowEmptyRsvpBlock( value ) }
							help={ __(
								'Toggle to show or hide the Empty RSVP block.',
								'gatherpress',
							) }
						/>
						<ToggleControl
							label={ __( 'Limit RSVP Display', 'gatherpress' ) }
							checked={ rsvpLimitEnabled }
							onChange={ () =>
								setAttributes( {
									rsvpLimitEnabled: ! rsvpLimitEnabled,
								} )
							}
							help={ __(
								'Enable to limit the number of RSVPs displayed in this block.',
								'gatherpress',
							) }
						/>
						{ rsvpLimitEnabled && (
							<NumberControl
								label={ __( 'RSVP Display Limit', 'gatherpress' ) }
								value={ rsvpLimit }
								onChange={ ( value ) =>
									setAttributes( {
										rsvpLimit: parseInt( value, 10 ) || 8,
									} )
								}
								min={ 1 }
								max={ 100 }
								help={ __(
									'Set the maximum number of RSVPs to display. Default is 8.',
									'gatherpress',
								) }
							/>
						) }
					</PanelBody>
				</InspectorControls>
				{ isEventPostType() && (
					<BlockControls>
						<ToolbarGroup>
							<ToolbarButton
								label={ __( 'Edit', 'gatherpress' ) }
								text={
									editMode
										? __( 'Preview', 'gatherpress' )
										: __( 'Edit', 'gatherpress' )
								}
								onClick={ onEditClick }
							/>
						</ToolbarGroup>
					</BlockControls>
				) }
				{ editMode && (
					<RsvpManager
						defaultStatus={ defaultStatus }
						setDefaultStatus={ setDefaultStatus }
					/>
				) }
				{ ! editMode && <InnerBlocks template={ TEMPLATE } /> }
			</BlockContextProvider>
		</div>
	);
};

export default Edit;
