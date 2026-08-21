/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { PanelBody } from '@wordpress/components';
import { dispatch, select } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { EVENT_REST_API } from '../../helpers/namespace';
import { getResolvedLabelPreview, getRsvpFilterKey } from './helpers';

/**
 * Edit Component
 *
 * @param {Object}   props                   Block properties.
 * @param {Object}   props.attributes        Block attributes.
 * @param {Function} props.setAttributes     Function to update attributes.
 * @param {string}   props.clientId          Unique ID of the block.
 * @param {Function} props.insertBlocksAfter Function to insert blocks after this block.
 * @param {Object}   props.context           Block context data.
 *
 * @return {JSX.Element} The rendered edit component.
 */
const Edit = ( {
	attributes,
	setAttributes,
	clientId,
	insertBlocksAfter,
	context,
} ) => {
	const { text } = attributes;
	const blockProps = useBlockProps();

	// The RSVP Response filter seeds its labels with a `%d` placeholder that is
	// only substituted on the front end, so the editor shows the raw token. Keep
	// the token in the content and surface what it resolves to instead.
	const rsvpFilterKey = getRsvpFilterKey( attributes.className );
	const postId = context?.postId ?? null;
	const [ rsvpCounts, setRsvpCounts ] = useState( null );

	useEffect( () => {
		if ( ! rsvpFilterKey || ! postId ) {
			setRsvpCounts( null );
			return;
		}

		let ignore = false;

		apiFetch( {
			path: `${ EVENT_REST_API }/rsvp-responses?post_id=${ postId }`,
		} )
			.then( ( response ) => {
				if ( ! ignore ) {
					setRsvpCounts( response.data );
				}
			} )
			.catch( () => {
				if ( ! ignore ) {
					setRsvpCounts( null );
				}
			} );

		return () => {
			ignore = true;
		};
	}, [ rsvpFilterKey, postId ] );

	const rsvpCount = rsvpCounts?.[ rsvpFilterKey ]?.count ?? 0;
	const resolvedLabel = rsvpFilterKey
		? getResolvedLabelPreview( text, rsvpCount )
		: null;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Dropdown Item Settings', 'gatherpress' ) }>
					<p>
						{ __(
							'This item behaves like a button if the link is set to "#".',
							'gatherpress',
						) }
					</p>
					{ resolvedLabel && (
						<p>
							{
								// translators: %d is the placeholder text to be used in the label.
								__(
									'Use %d as a placeholder for the response count.',
									'gatherpress',
								)
							}{ ' ' }
							{ sprintf(
								// translators: %s is the label as visitors see it, with the count substituted.
								__( 'Visitors see “%s”.', 'gatherpress' ),
								resolvedLabel,
							) }
						</p>
					) }
				</PanelBody>
			</InspectorControls>
			<RichText
				{ ...blockProps }
				tagName="div"
				value={ text }
				onChange={ ( value ) => {
					// Parse the content and clean it up.
					const parser = new DOMParser();
					const parsedDoc = parser.parseFromString(
						value,
						'text/html',
					);
					const anchors = parsedDoc.querySelectorAll( 'a' );

					// Default fallback anchor tag.
					let openingTag = '<a href="#">';
					const closingTag = '</a>';

					if ( 0 < anchors.length ) {
						// Extract the opening tag from the first anchor.
						const firstAnchor = anchors[ 0 ];

						// Capture attributes.
						const href = firstAnchor.getAttribute( 'href' ) || '#';
						const rel = firstAnchor.getAttribute( 'rel' );
						const target = firstAnchor.getAttribute( 'target' );

						// Start building the opening tag.
						openingTag = `<a href="${ href }"`;

						// Add rel attribute if it exits.
						if ( rel ) {
							openingTag += ` rel="${ rel }"`;
						}

						// Add target attribute if it exists.
						if ( target ) {
							openingTag += ` target="${ target }"`;
						}

						// Close the opening tag.
						openingTag += '>';
					}

					// Remove all markup and clean text.
					const cleanText = parsedDoc.body.textContent.trim();

					// Wrap the clean text with the anchor tags.
					let newText;
					if ( cleanText ) {
						newText = `${ openingTag }${ cleanText }${ closingTag }`;
					} else {
						newText = '';
					}

					// Update attributes with the cleaned-up values.
					setAttributes( { text: newText } );

					// Update metadata for List View.
					dispatch( 'core/block-editor' ).updateBlockAttributes(
						clientId,
						{
							metadata: {
								name:
									cleanText ||
									__( 'Dropdown Item', 'gatherpress' ),
							},
						},
					);
				} }
				placeholder={ __( 'Item Text…', 'gatherpress' ) }
				allowedFormats={ [ 'core/link' ] }
				onKeyDown={ ( event ) => {
					if ( 'Enter' === event.key ) {
						event.preventDefault();
						const newBlock = createBlock(
							'gatherpress/dropdown-item',
							{ text: '' },
						);
						insertBlocksAfter( [ newBlock ] );
					}

					if ( 'Backspace' === event.key && ! attributes.text ) {
						event.preventDefault();

						// Retrieve block order and index.
						const { getBlockOrder, getBlockIndex } =
							select( 'core/block-editor' );
						const { removeBlock, selectBlock } =
							dispatch( 'core/block-editor' );

						const blockOrder = getBlockOrder();
						const currentIndex = getBlockIndex( clientId );

						// Check if there's a previous block.
						if ( 0 < currentIndex ) {
							const previousBlockId =
								blockOrder[ currentIndex - 1 ];

							// Focus the previous block and set the caret to the end.
							selectBlock( previousBlockId, -1 );

							// Remove the current block.
							removeBlock( clientId );
						}
					}
				} }
			/>
		</>
	);
};

export default Edit;
