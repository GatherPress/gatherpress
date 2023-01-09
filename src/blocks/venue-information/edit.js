/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
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
    TextControl,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import VenueInformation from '../../components/VenueInformation';

import GoogleMapEmbed from './google-map';

const Edit = ({ attributes, clientId, isSelected, setAttributes }) => {
    const {
        mapId,
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
    const editPost = useDispatch('core/editor').editPost;
    let venueInformationMetaData = useSelect(
        (select) =>
        select('core/editor').getEditedPostAttribute('meta')
                ._venue_information
    );

if (venueInformationMetaData) {
    venueInformationMetaData = JSON.parse(venueInformationMetaData);
} else {
    venueInformationMetaData = {};
}

    const onUpdate = (key, value) => {
        const payload = JSON.stringify(
            {
                ...venueInformationMetaData,
                [key]: value,
            }
        );
        const meta = { _venue_information: payload };

        setAttributes({ [key]: value });
        editPost({ meta });
    };

    useEffect(
        () => {
            setAttributes(
            {
                    fullAddress: venueInformationMetaData.fullAddress ?? '',
                    phoneNumber: venueInformationMetaData.phoneNumber ?? '',
                    website: venueInformationMetaData.website ?? '',
                }
        );
        }, []
    );

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Venue Settings', 'gatherpress')}>
                    <TextControl
                        label={__('Venue Street Address', 'gatherpress')}
                        value={fullAddress}
                        onChange={(place) =>
                            setAttributes({ fullAddress: place })
                        }
                        placeholder={__('Enter address', 'gatherpress')}
                    />
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
                                setAttributes(
                                    {
                                        device: 'desktop',
                                    }
                                )
                            }
                        >
                            <span className="dashicons dashicons-desktop"></span>
                        </Button>
                        <Button
                            isSmall={true}
                            isPressed={device === 'tablet'}
                            onClick={() =>
                                setAttributes(
                                    {
                                        device: 'tablet',
                                    }
                                )
                            }
                        >
                            <span className="dashicons dashicons-tablet"></span>
                        </Button>
                        <Button
                            isSmall={true}
                            isPressed={device === 'mobile'}
                            onClick={() =>
                                setAttributes(
                                    {
                                        device: 'mobile',
                                    }
                                )
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
                {!isSelected && (
                    <>
                    {!fullAddress && !phoneNumber && !website && (
                        <Flex justify="normal">
                        <FlexItem display="flex">
                        <Icon icon="location" />
                        </FlexItem>
                        <FlexItem>
                        <em>
                        {__(
                            'Add venue information.',
                            'gatherpress'
                        )}
                                    </em>
                                </FlexItem>
                     </Flex>
                    )}
                        <VenueInformation
                            fullAddress={fullAddress}
                            phoneNumber={phoneNumber}
                            website={website}
                        />
                        <GoogleMapEmbed
                            location={fullAddress}
                            zoom={zoom}
                            type={type}
                            height={deskHeight}
                            className={`emb__height_${mapId}`}
                        />
                    </>
                )}
                {isSelected && (
                    <>
                    <Flex>
                    <FlexBlock>
                                <TextControl
                                    label={__('Full Address', 'gatherpress')}
                                    value={fullAddress}
                                    onChange={(value) => {
                                        onUpdate('fullAddress', value);
                                        }}
                                />
                            </FlexBlock>
                        </Flex>
                        <Flex>
                            <FlexBlock>
                                <TextControl
                                    label={__('Phone Number', 'gatherpress')}
                                    value={phoneNumber}
                                    onChange={(value) => {
                                        onUpdate('phoneNumber', value);
                                        }}
                                />
                            </FlexBlock>
                            <FlexBlock>
                                <TextControl
                                    label={__('Website', 'gatherpress')}
                                    value={website}
                                    type="url"
                                    onChange={(value) => {
                                        onUpdate('website', value);
                                        }}
                                />
                            </FlexBlock>
                        </Flex>
                        <Flex>
                            <FlexBlock>
                                <GoogleMapEmbed
                                    location={fullAddress}
                                    zoom={zoom}
                                    type={type}
                                    height={deskHeight}
                                    className={`emb__height_${mapId}`}
                                />
                            </FlexBlock>
                        </Flex>
                    </>
                )}
    </div>
    </>
    );
};

export default Edit;
