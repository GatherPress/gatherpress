/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PanelRow, SelectControl } from '@wordpress/components';
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
		<PanelRow>
			<SelectControl
				__next40pxDefaultSize
				label={ __( 'Append time zone', 'gatherpress' ) }
				value={ preference }
				options={ [
					{ label: __( 'Default', 'gatherpress' ), value: '' },
					{ label: __( 'Always', 'gatherpress' ), value: 'always' },
					{ label: __( 'Never', 'gatherpress' ), value: 'never' },
				] }
				help={ __(
					'Overrides the Event Date block and the site setting for this event.',
					'gatherpress'
				) }
				onChange={ updatePreference }
			/>
		</PanelRow>
	);
};

export default ShowTimezone;
