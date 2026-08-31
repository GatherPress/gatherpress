/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * ShowTimezone component.
 *
 * Whether this event names its timezone, stored as the
 * `gatherpress_show_timezone` post meta.
 *
 * Lives on the event rather than only on the Event Date block because a block
 * in a site template renders every event and cannot answer this per event —
 * and an event whose date is rendered by such a template has no block of its
 * own to configure.
 *
 * @since 0.36.0
 *
 * @return {JSX.Element} A select for the event's timezone preference.
 */
const ShowTimezone = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );

	const preference = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'meta' )
				?.gatherpress_show_timezone || '',
		[]
	);

	const updatePreference = useCallback(
		( value ) => {
			editPost( { meta: { gatherpress_show_timezone: value } } );
			unlockPostSaving();
		},
		[ editPost, unlockPostSaving ]
	);

	return (
		<SelectControl
			__next40pxDefaultSize
			label={ __( 'Append time zone', 'gatherpress' ) }
			value={ preference }
			onChange={ updatePreference }
			help={ __(
				'Overrides the Event Date block and the site setting for this event.',
				'gatherpress'
			) }
			__nexthasnomarginbottom
		>
			<option value="">{ __( 'Default', 'gatherpress' ) }</option>
			<option value="always">{ __( 'Always', 'gatherpress' ) }</option>
			<option value="never">{ __( 'Never', 'gatherpress' ) }</option>
		</SelectControl>
	);
};

export default ShowTimezone;
