/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { getCurrentContextualPostId } from '../../helpers/editor';
import { PT_VENUE } from '../../helpers/namespace';
import MapEmbed from '../../components/MapEmbed';

/**
 * Edit component for the Venue Map block.
 *
 * @since 1.0.0
 *
 * @param {Object}   props               - Component properties.
 * @param {Object}   props.attributes    - Block attributes.
 * @param {Function} props.setAttributes - Function to set block attributes.
 * @param {Object}   props.context       - Block context.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = ( { attributes, setAttributes, context } ) => {
	const { zoom, type, height } = attributes;
	const blockProps = useBlockProps();

	// Get the venue post ID from context (provided by venue-v2 block).
	const venuePostId = getCurrentContextualPostId( context?.postId );

	// Get venue meta fields from the venue post.
	const { fullAddress, latitude, longitude } = useSelect(
		( select ) => {
			if ( ! venuePostId ) {
				return {
					fullAddress: '',
					latitude: '',
					longitude: '',
				};
			}

			const { getEntityRecord } = select( 'core' );
			const venuePost = getEntityRecord( 'postType', PT_VENUE, venuePostId );

			if ( ! venuePost ) {
				return {
					fullAddress: '',
					latitude: '',
					longitude: '',
				};
			}

			return {
				fullAddress: venuePost.meta?.gatherpress_venue_address || '',
				latitude: venuePost.meta?.gatherpress_venue_latitude || '',
				longitude: venuePost.meta?.gatherpress_venue_longitude || '',
			};
		},
		[ venuePostId ]
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Map settings', 'gatherpress' ) }>
					<RangeControl
						label={ __( 'Zoom level', 'gatherpress' ) }
						value={ zoom }
						onChange={ ( value ) =>
							setAttributes( { zoom: value } )
						}
						min={ 1 }
						max={ 20 }
					/>
					<SelectControl
						label={ __( 'Map type', 'gatherpress' ) }
						value={ type }
						options={ [
							{
								label: __( 'Roadmap', 'gatherpress' ),
								value: 'roadmap',
							},
							{
								label: __( 'Satellite', 'gatherpress' ),
								value: 'satellite',
							},
							{
								label: __( 'Hybrid', 'gatherpress' ),
								value: 'hybrid',
							},
							{
								label: __( 'Terrain', 'gatherpress' ),
								value: 'terrain',
							},
						] }
						onChange={ ( value ) => setAttributes( { type: value } ) }
					/>
					<RangeControl
						label={ __( 'Height (px)', 'gatherpress' ) }
						value={ height }
						onChange={ ( value ) =>
							setAttributes( { height: value } )
						}
						min={ 100 }
						max={ 800 }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ fullAddress ? (
					<div className="block-editor-inner-blocks">
						<MapEmbed
							location={ fullAddress }
							latitude={ latitude }
							longitude={ longitude }
							zoom={ zoom }
							type={ type }
							height={ height }
						/>
					</div>
				) : (
					<div className="gatherpress-venue-map__placeholder">
						<p>
							{ __(
								'No venue address available. Please add a venue address to display the map.',
								'gatherpress'
							) }
						</p>
					</div>
				) }
			</div>
		</>
	);
};

export default Edit;
