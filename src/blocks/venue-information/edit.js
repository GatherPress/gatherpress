/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, Fragment } from '@wordpress/element';

import {
	Button,
	ButtonGroup,
	Flex,
	FlexBlock,
	FlexItem,
	Icon,
	PanelBody,
	RadioControl,
	RangeControl,
	TextareaControl,
	TextControl
} from '@wordpress/components';

/**
 * Internal dependencies.
 */
import VenueInformation from '../../components/VenueInformation';

import GoogleMap from './googlemap';

const Edit = ( { attributes, setAttributes, isSelected, clientId } ) => {
	const {
		blockId,
		fullAddress,
		phoneNumber,
		website,
		zoom,
		type,
		deskHeight,
		tabHeight,
		mobileHeight,
		device,
	} = attributes;

	const blockProps = useBlockProps();

	setAttributes({
		blockId: clientId.slice(0, 8),
	});

	const editPost = useDispatch( 'core/editor' ).editPost;

	let venueInformationMetaData = useSelect(
		( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' )._venue_information,
	);

	if ( venueInformationMetaData ) {
		venueInformationMetaData = JSON.parse( venueInformationMetaData );
	} else {
		venueInformationMetaData = {};
	}

	const onUpdate = ( key, value ) => {
		const payload = JSON.stringify( { ...venueInformationMetaData, [ key ]: value } );
		const meta = { _venue_information: payload };

		setAttributes( { [ key ]: value } );
		editPost( { meta } );
	};

	useEffect( () => {
		setAttributes( {
			fullAddress: venueInformationMetaData.fullAddress ?? '',
			phoneNumber: venueInformationMetaData.phoneNumber ?? '',
			website: venueInformationMetaData.website ?? '',
		} );
	}, [] );

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody title={__('Map Settings', 'gatherpress')}>
					<RangeControl
						label={__('Zoom Level', 'gatherpress')}
						beforeIcon="search"
						value={zoom}
						onChange={(value) => setAttributes({ zoom: value })}
						min={1}
						max={22}
					/>
					<RadioControl
						label={__('Map Type', 'gatherpress')}
						selected={type}
						options={[
							{
								label: __('Roadmap', 'gatherpress'),
								value: 'm',
							},
							{
								label: __('Satellite', 'gatherpress'),
								value: 'k',
							},
						]}
						onChange={(value) => {
							setAttributes({ type: value });
						}}
					/>
					<ButtonGroup
						style={{ marginBottom: '10px', float: 'right' }}
					>
						<Button
							isSmall={true}
							isPressed={device === 'desktop'}
							onClick={() =>
								setAttributes({
									device: 'desktop',
								})
							}
						>
							<span className="dashicons dashicons-desktop"></span>
						</Button>
						<Button
							isSmall={true}
							isPressed={device === 'tablet'}
							onClick={() =>
								setAttributes({
									device: 'tablet',
								})
							}
						>
							<span className="dashicons dashicons-tablet"></span>
						</Button>
						<Button
							isSmall={true}
							isPressed={device === 'mobile'}
							onClick={() =>
								setAttributes({
									device: 'mobile',
								})
							}
						>
							<span className="dashicons dashicons-smartphone"></span>
						</Button>
					</ButtonGroup>
					{device === 'desktop' && (
						<RangeControl
							label={__('Map Height', 'gatherpress')}
							beforeIcon="desktop"
							value={deskHeight}
							onChange={(height) =>
								setAttributes({ deskHeight: height })
							}
							min={1}
							max={2000}
						/>
					)}
					{device === 'tablet' && (
						<RangeControl
							label={__('Map Height', 'gatherpress')}
							beforeIcon="tablet"
							value={tabHeight}
							onChange={(height) =>
								setAttributes({ tabHeight: height })
							}
							min={1}
							max={2000}
						/>
					)}
					{device === 'mobile' && (
						<RangeControl
							label={__('Map Height', 'gatherpress')}
							beforeIcon="smartphone"
							value={mobileHeight}
							onChange={(height) =>
								setAttributes({ mobileHeight: height })
							}
							min={1}
							max={2000}
						/>
					)}
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				{ ! isSelected && (
					<>
						{ ( ! fullAddress && ! phoneNumber && ! website ) && (
							<Flex justify="normal">
								<FlexItem display="flex">
									<Icon icon="location" />
								</FlexItem>
								<FlexItem>
									<em>{ __( 'Add venue information.', 'gatherpress' ) }</em>
								</FlexItem>
							</Flex>
						) }
						<VenueInformation fullAddress={ fullAddress } phoneNumber={ phoneNumber } website={ website } />
					</>
				) }
				{ isSelected && (
					<>
						<Flex>
							<FlexBlock>
								<TextControl
									label={ __( 'Full Address', 'gatherpress' ) }
									value={ fullAddress }
									onChange={ ( value ) => {
										onUpdate( 'fullAddress', value );
									} }
								/>
							</FlexBlock>
						</Flex>
						<Flex>
							<FlexBlock>
								<TextControl
									label={ __( 'Phone Number', 'gatherpress' ) }
									value={ phoneNumber }
									onChange={ ( value ) => {
										onUpdate( 'phoneNumber', value );
									} }
								/>
							</FlexBlock>
							<FlexBlock>
								<TextControl
									label={ __( 'Website', 'gatherpress' ) }
									value={ website }
									type="url"
									onChange={ ( value ) => {
										onUpdate( 'website', value );
									} }
								/>
							</FlexBlock>
						</Flex>
					</>
				) }
				{ fullAddress && (
					<>
						<Flex>
							<FlexBlock>
								<GoogleMap
									location={fullAddress}
									zoom={zoom}
									type={type}
									height={deskHeight}
									className={`emb__height_${blockId}`}
								/>
								{/* <GoogleMap
									location={fullAddress}
									zoom='10'
									type="m"
									height='400px'
									className={`emb__height_${blockId}`}
								/> */}
							</FlexBlock>
						</Flex>
					</>
				) }
			</div>
		</Fragment>
	);
};

export default Edit;
