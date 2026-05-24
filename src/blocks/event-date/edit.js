/**
 * External dependencies
 */
import moment from 'moment';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	BlockControls,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
	PanelBody,
	RadioControl,
	Spinner,
	TextControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	convertPHPToMomentFormat,
	createMomentWithTimezone,
	getTimezone,
	getUtcOffset,
	isManualOffset,
	removeNonTimePHPFormatChars,
} from '../../helpers/datetime';
import DateTimeRange from '../../components/DateTimeRange';
import { getFromSettings } from '../../helpers/editor-settings';
import {
	isEventPostType,
	findEventPostById,
	DISABLED_FIELD_OPACITY,
} from '../../helpers/event';
import { isInFSETemplate } from '../../helpers/editor';

/**
 * Similar to get_display_datetime method in class-event.php.
 *
 * @param {string} dateTimeStart
 * @param {string} dateTimeEnd
 * @param {string} timezone
 * @param {string} startFormat
 * @param {string} endFormat
 * @param {string} separator
 * @param {string} showTimezone
 *
 * @return {string} Displayed date.
 */
const displayDateTime = (
	dateTimeStart,
	dateTimeEnd,
	timezone,
	startFormat,
	endFormat,
	separator,
	showTimezone
) => {
	const dateFormat = getFromSettings( 'dateFormat' );
	const timeFormat = getFromSettings( 'timeFormat' );
	const globalShowTimezone = getFromSettings( 'showTimezone' );
	const fullFormat = `${ dateFormat } ${ timeFormat }`;

	timezone = getTimezone( timezone );
	let sameStartEndDay = false;

	// Check for default formatting with same event day before applying
	// attribute-specific formats.
	if ( dateTimeStart && dateTimeEnd ) {
		const sameDayFormat = convertPHPToMomentFormat( dateFormat );
		sameStartEndDay =
			createMomentWithTimezone( dateTimeStart, timezone ).format( sameDayFormat ) ===
			createMomentWithTimezone( dateTimeEnd, timezone ).format( sameDayFormat );
	}

	const parts = [];

	// Add start date/time.
	if ( dateTimeStart ) {
		startFormat = convertPHPToMomentFormat(
			startFormat || fullFormat
		);
		parts.push( createMomentWithTimezone( dateTimeStart, timezone ).format( startFormat ) );
	}

	// Determine end date/time.
	if ( dateTimeEnd ) {
		// Fall formatting back to default.
		endFormat = endFormat || fullFormat;

		// Remove non-time characters from PHP date format if start and end
		// are on the same day.
		endFormat = sameStartEndDay ? removeNonTimePHPFormatChars( endFormat ) : endFormat;

		// There may be no valid PHP date/time chars left after the removal.
		if ( ! endFormat ) {
			dateTimeEnd = false;
		}
	}

	// Add separator if start + end date/time(s).
	if ( dateTimeStart && dateTimeEnd ) {
		parts.push( 'to' === separator ? __( 'to', 'gatherpress' ) : separator );
	}

	// Add end date/time.
	if ( dateTimeEnd && endFormat ) {
		endFormat = convertPHPToMomentFormat( endFormat );
		parts.push( createMomentWithTimezone( dateTimeEnd, timezone ).format( endFormat ) );
	}

	// Add timezone.
	if ( showTimezone ? 'yes' === showTimezone : globalShowTimezone ) {
		if ( isManualOffset( timezone ) ) {
			// For manual offsets, display them as GMT+/-offset.
			// Convert +05:30 to GMT+0530, -04:30 to GMT-0430, +00:00 to GMT+0000.
			const sign = timezone.charAt( 0 );
			const offset = timezone.substring( 1 ).replace( ':', '' );
			parts.push( `GMT${ sign }${ offset }` );
		} else {
			// For IANA timezones, use the timezone abbreviation.
			parts.push(
				createMomentWithTimezone( dateTimeEnd || dateTimeStart, timezone )
					.format( 'z' )
			);
		}
	}

	// Add UTC offset if GMT (invalid site timezone).
	parts.push( getUtcOffset( timezone ) );

	// The filter removes empty values.
	return parts.filter( Boolean ).join( ' ' );
};

/**
 * Calculate the new display type when toggling start/end date visibility.
 *
 * @param {string}  toggleType    - Which date to toggle: 'start' or 'end'.
 * @param {boolean} showStartTime - Whether start time is currently shown.
 * @param {boolean} showEndTime   - Whether end time is currently shown.
 *
 * @return {string} New display type value.
 */
const calculateDisplayType = ( toggleType, showStartTime, showEndTime ) => {
	if ( 'start' === toggleType ) {
		// Toggling start date.
		if ( showEndTime ) {
			return showStartTime ? 'end' : 'both';
		}
		return 'start';
	}

	// Toggling end date.
	if ( showStartTime ) {
		return showEndTime ? 'start' : 'both';
	}
	return 'end';
};

/**
 * Edit component for the GatherPress Event Date block.
 *
 * This component represents the editable view of the GatherPress Event Date block
 * in the WordPress block editor. It manages the state of the start and end date,
 * time, and timezone for the block, and renders the user interface accordingly.
 * The component includes a BlockControls toolbar, displays the formatted date and
 * time, and provides controls for editing the date and time range via the
 * DateTimeRange component within InspectorControls.
 *
 * @since 0.27.0
 *
 * @param {Object}   root0               The props passed to the Edit component.
 * @param {Object}   root0.attributes    The block attributes.
 * @param {Object}   root0.context       Block context data containing postId and event info.
 * @param {Function} root0.setAttributes Function to set block attributes.
 *
 * @return {JSX.Element} The rendered Edit component for the GatherPress Event Date block.
 *
 * @see {@link DateTimeRange} - Component for editing date and time range.
 * @see {@link useBlockProps} - Custom hook for block props.
 * @see {@link displayDateTime} - Function for formatting and displaying date and time.
 */
const Edit = ( { attributes, setAttributes, context } ) => {
	const {
		displayType,
		startDateFormat,
		endDateFormat,
		separator,
		showTimezone,
	} = attributes;

	const dateFormat = getFromSettings( 'dateFormat' );
	const timeFormat = getFromSettings( 'timeFormat' );
	const defaultShowTimezone = getFromSettings( 'showTimezone' );

	// Defer the supports check to useSelect so it stays reactive.
	const postId = attributes?.postId ?? context?.postId ?? null;
	const hasExplicitOverride = !! attributes?.postId;

	const { dateTimeStart, dateTimeEnd, timezone, isLoading, isValidEvent } = useSelect(
		( select ) => {
			// Resolve the post type from context or fall back to the editor's current post type.
			// This ensures custom post types with gatherpress-event-date support work correctly
			// in Query Loop blocks and postId override scenarios.
			const postType =
				context?.postType ||
				select( 'core/editor' )?.getCurrentPostType();

			// Reactive supports check — `getPostType` is read inside useSelect so the
			// component re-renders once the post-type definition (and its supports) load.
			const supportsEventDate = !! select( 'core' )
				.getPostType( postType )?.supports?.[ 'gatherpress-event-date' ];

			if ( ! postId ) {
				return { isValidEvent: false };
			}

			// When editing an event directly, use the datetime store for live updates.
			if ( isEventPostType() ) {
				const datetimeStore = select( 'gatherpress/datetime' );
				return {
					dateTimeStart: datetimeStore.getDateTimeStart(),
					dateTimeEnd: datetimeStore.getDateTimeEnd(),
					timezone: datetimeStore.getTimezone(),
					isValidEvent: supportsEventDate,
				};
			}

			// Postid override on a host that itself doesn't support event-date
			// (e.g. a regular page or template part). Resolve the override target
			// across event-supporting post types so the block can light up.
			if ( hasExplicitOverride && ! supportsEventDate ) {
				const overridePost = findEventPostById( select, postId );
				if ( ! overridePost ) {
					return { isValidEvent: false };
				}
				const overrideMeta = overridePost?.meta;
				return {
					dateTimeStart: overrideMeta?.gatherpress_datetime_start,
					dateTimeEnd: overrideMeta?.gatherpress_datetime_end,
					timezone: overrideMeta?.gatherpress_timezone,
					isValidEvent: true,
				};
			}

			// Short-circuit before checking resolution: if the context post type doesn't
			// support event-date we never call `getEntityRecord`, so its resolver never
			// fires and `hasFinishedResolution` would stay false forever — blocking the
			// component on a spinner that never resolves.
			if ( ! supportsEventDate ) {
				return { isValidEvent: false };
			}

			// For Query Loop and override contexts, fetch from entity record.
			const hasResolved = select( 'core' ).hasFinishedResolution(
				'getEntityRecord',
				[ 'postType', postType, postId ]
			);

			if ( ! hasResolved ) {
				return { isLoading: true, isValidEvent: false };
			}

			const post = select( 'core' ).getEntityRecord(
				'postType',
				postType,
				postId
			);
			const meta = post?.meta;

			// Match the original `hasValidEventId` semantics: only treat the block as
			// "connected" when the referenced post is published. Drafts/scheduled posts
			// referenced via Query Loop or postId override stay dim by design.
			return {
				dateTimeStart: meta?.gatherpress_datetime_start,
				dateTimeEnd: meta?.gatherpress_datetime_end,
				timezone: meta?.gatherpress_timezone,
				isValidEvent: !! post && 'publish' === post?.status,
			};
		},
		[ postId, context?.postType, hasExplicitOverride ]
	);

	const blockProps = useBlockProps( {
		style: {
			opacity: ( isInFSETemplate() || isValidEvent ) ? 1 : DISABLED_FIELD_OPACITY,
		},
	} );

	// Show spinner only while loading, not on 404.
	if ( isLoading ) {
		return (
			<div { ...blockProps }>
				<Spinner />
			</div>
		);
	}

	// If we have a postId but no valid event data (404 or invalid event),
	// fall back to today's date to show a normal appearance.
	const fallbackDateTime = createMomentWithTimezone(
		moment().format( 'YYYY-MM-DD HH:mm:ss' ),
		getTimezone()
	);
	const finalDateTimeStart = dateTimeStart || fallbackDateTime.format();
	const finalDateTimeEnd = dateTimeEnd || fallbackDateTime.clone().add( 1, 'hour' ).format();
	const finalTimezone = timezone || getTimezone();

	const showStartTime = [ 'start', 'both' ].includes( displayType );
	const showEndTime = [ 'end', 'both' ].includes( displayType );

	return (
		<div { ...blockProps }>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						label={ __( 'Toggle start date', 'gatherpress' ) }
						text={ __( 'Start', 'gatherpress' ) }
						isPressed={ showStartTime }
						onClick={ () => {
							setAttributes( {
								displayType: calculateDisplayType(
									'start',
									showStartTime,
									showEndTime
								),
							} );
						} }
					/>
					<ToolbarButton
						label={ __( 'Toggle end date', 'gatherpress' ) }
						text={ __( 'End', 'gatherpress' ) }
						isPressed={ showEndTime }
						onClick={ () => {
							setAttributes( {
								displayType: calculateDisplayType(
									'end',
									showStartTime,
									showEndTime
								),
							} );
						} }
					/>
				</ToolbarGroup>
			</BlockControls>
			{ displayDateTime(
				showStartTime ? finalDateTimeStart : null,
				showEndTime ? finalDateTimeEnd : null,
				finalTimezone,
				startDateFormat,
				endDateFormat,
				separator,
				showTimezone
			) }
			{ isEventPostType() && (
				<InspectorControls>
					<PanelBody>
						<VStack spacing={ 4 }>
							<DateTimeRange />
						</VStack>
					</PanelBody>
				</InspectorControls>
			) }
			<InspectorControls>
				<PanelBody
					title={ __( 'Display Settings', 'gatherpress' ) }
					initialOpen={ true }
				>
					<RadioControl
						label={ __( 'Display', 'gatherpress' ) }
						selected={ displayType }
						options={ [
							{
								label: __(
									'Start and end date',
									'gatherpress'
								),
								value: 'both',
							},
							{
								label: __( 'Start date only', 'gatherpress' ),
								value: 'start',
							},
							{
								label: __( 'End date only', 'gatherpress' ),
								value: 'end',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { displayType: value } )
						}
					/>
					{ 'both' === displayType && (
						<TextControl
							label={ __( 'Separator', 'gatherpress' ) }
							value={ separator }
							placeholder={ __( 'to', 'gatherpress' ) }
							onChange={ ( value ) =>
								setAttributes( { separator: value } )
							}
						/>
					) }
					{ showStartTime && (
						<TextControl
							label={ __( 'Start date format', 'gatherpress' ) }
							value={ startDateFormat }
							placeholder={ `${ dateFormat } ${ timeFormat }` }
							onChange={ ( value ) =>
								setAttributes( { startDateFormat: value } )
							}
						/>
					) }
					{ showEndTime && (
						<TextControl
							label={ __( 'End date format', 'gatherpress' ) }
							value={ endDateFormat }
							placeholder={ `${ dateFormat } ${ timeFormat }` }
							onChange={ ( value ) =>
								setAttributes( { endDateFormat: value } )
							}
						/>
					) }
					<p className="components-base-control__help">
						<a
							href="https://wordpress.org/documentation/article/customize-date-and-time-format/"
							target="_blank"
							rel="noreferrer"
						>
							{ __(
								'Date/time formatting documentation',
								'gatherpress'
							) }
						</a>
					</p>
					<ToggleControl
						label={ __( 'Append time zone', 'gatherpress' ) }
						checked={
							showTimezone
								? 'yes' === showTimezone
								: defaultShowTimezone
						}
						onChange={ ( value ) =>
							setAttributes( {
								showTimezone: value ? 'yes' : 'no',
							} )
						}
					/>
				</PanelBody>
			</InspectorControls>
		</div>
	);
};

export default Edit;
