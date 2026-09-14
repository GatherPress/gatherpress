/**
 * WordPress dependencies
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	ComboboxControl,
	PanelBody,
	RadioControl,
	SelectControl,
	TextControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Internal dependencies
 */
import TopicSelect from '../../components/TopicSelect';
import { getFromConfig } from '../../helpers/editor-settings';
import { getVenuePostType, useVenueOptions } from '../../helpers/venue';

/**
 * The feed scopes the block can render.
 *
 * @since 0.36.0
 *
 * @type {Object[]}
 */
const SCOPE_OPTIONS = [
	{
		label: __( 'All events on the site', 'gatherpress' ),
		value: 'sitewide',
	},
	{
		label: __( 'An events archive', 'gatherpress' ),
		value: 'archive',
	},
	{
		label: __( 'Events at one venue', 'gatherpress' ),
		value: 'venue',
	},
	{
		label: __( 'Events in one topic', 'gatherpress' ),
		value: 'topic',
	},
];

/**
 * The link flavors the block can render.
 *
 * @since 0.36.0
 *
 * @type {Object[]}
 */
const LINK_FORMAT_OPTIONS = [
	{
		label: __( 'iCal and subscribe links', 'gatherpress' ),
		value: 'both',
	},
	{
		label: __( 'iCal link only', 'gatherpress' ),
		value: 'ical',
	},
	{
		label: __( 'Subscribe link only', 'gatherpress' ),
		value: 'webcal',
	},
];

/**
 * A searchable venue picker for the venue feed scope.
 *
 * @since 0.36.0
 *
 * @param {Object}   props          Component props.
 * @param {number}   props.value    Currently selected venue post ID.
 * @param {Function} props.onChange Called with the selected venue post ID, or null when cleared.
 *
 * @return {JSX.Element} The venue picker.
 */
function VenueSelect( { value, onChange } ) {
	const [ search, setSearch ] = useState( '' );

	// Venue feeds are keyed by the venue post, not by a shadow term.
	const { venueOptions } = useVenueOptions(
		search,
		value,
		'postType',
		getVenuePostType()
	);

	const setSearchDebounced = useDebounce( setSearch, 300 );

	return (
		<ComboboxControl
			__next40pxDefaultSize
			label={ __( 'Venue', 'gatherpress' ) }
			value={ value || null }
			options={ venueOptions }
			onChange={ onChange }
			onFilterValueChange={ setSearchDebounced }
		/>
	);
}

/**
 * The Subscribe to Events block edit component.
 *
 * @since 0.36.0
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Setter for the block attributes.
 *
 * @return {JSX.Element} The block editor UI.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		scope,
		postType,
		venueId,
		topicId,
		linkFormat,
		subscribeText,
		linkText,
	} = attributes;

	// Event post types come from the editor config so a companion post type
	// shows up without anything else declaring it. Read inside the memo so the
	// options array is not rebuilt on every render.
	const postTypeOptions = useMemo(
		() =>
			( getFromConfig( 'eventPostTypes' ) ?? [] ).map( ( slug ) => ( {
				label: slug,
				value: slug,
			} ) ),
		[]
	);

	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Feed', 'gatherpress' ) }
					initialOpen={ true }
				>
					<VStack spacing={ 4 }>
						<RadioControl
							label={ __( 'Which events?', 'gatherpress' ) }
							selected={ scope }
							options={ SCOPE_OPTIONS }
							onChange={ ( value ) => setAttributes( { scope: value } ) }
						/>

						{ 'archive' === scope && (
							<SelectControl
								__next40pxDefaultSize
								label={ __( 'Event archive', 'gatherpress' ) }
								value={ postType }
								options={ [
									{
										label: __( 'Default', 'gatherpress' ),
										value: '',
									},
									...postTypeOptions,
								] }
								onChange={ ( value ) =>
									setAttributes( { postType: value } )
								}
							/>
						) }

						{ 'venue' === scope && (
							<VenueSelect
								value={ venueId }
								onChange={ ( value ) =>
									setAttributes( { venueId: value ?? 0 } )
								}
							/>
						) }

						{ 'topic' === scope && (
							<TopicSelect
								value={ topicId }
								onChange={ ( value ) =>
									setAttributes( { topicId: value ?? 0 } )
								}
							/>
						) }
					</VStack>
				</PanelBody>

				<PanelBody
					title={ __( 'Links', 'gatherpress' ) }
					initialOpen={ true }
				>
					<VStack spacing={ 4 }>
						<RadioControl
							label={ __( 'Links to show', 'gatherpress' ) }
							selected={ linkFormat }
							options={ LINK_FORMAT_OPTIONS }
							onChange={ ( value ) =>
								setAttributes( { linkFormat: value } )
							}
						/>

						<TextControl
							__next40pxDefaultSize
							label={ __( 'iCal link text', 'gatherpress' ) }
							value={ linkText }
							placeholder={ __( 'iCal feed', 'gatherpress' ) }
							onChange={ ( value ) =>
								setAttributes( { linkText: value } )
							}
						/>

						<TextControl
							__next40pxDefaultSize
							label={ __( 'Subscribe link text', 'gatherpress' ) }
							value={ subscribeText }
							placeholder={ __( 'Subscribe', 'gatherpress' ) }
							onChange={ ( value ) =>
								setAttributes( { subscribeText: value } )
							}
						/>
					</VStack>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="gatherpress/subscribe-to-events"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
