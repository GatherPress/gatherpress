/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * DoorsTime component.
 *
 * When the doors open and close, stored as 'H:i' times in the
 * `gatherpress_doors_open` and `gatherpress_doors_close` post meta. Both are
 * optional, and clearing a field removes that time.
 *
 * @since TBD
 *
 * @return {JSX.Element} Time fields for the doors open and close times.
 */
const DoorsTime = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );

	const { doorsOpen, doorsClose } = useSelect( ( select ) => {
		const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );

		return {
			doorsOpen: meta?.gatherpress_doors_open || '',
			doorsClose: meta?.gatherpress_doors_close || '',
		};
	}, [] );

	const updateTime = useCallback(
		( key, value ) => {
			editPost( { meta: { [ key ]: value } } );
			unlockPostSaving();
		},
		[ editPost, unlockPostSaving ],
	);

	return (
		<>
			<TextControl
				__next40pxDefaultSize
				type="time"
				label={ __( 'Doors open', 'gatherpress' ) }
				value={ doorsOpen }
				onChange={ ( value ) => updateTime( 'gatherpress_doors_open', value ) }
				help={ __(
					'Optional. A time later than the start means the day before.',
					'gatherpress',
				) }
			/>
			<TextControl
				__next40pxDefaultSize
				type="time"
				label={ __( 'Doors close', 'gatherpress' ) }
				value={ doorsClose }
				onChange={ ( value ) => updateTime( 'gatherpress_doors_close', value ) }
				help={ __(
					'Optional. A time earlier than the end means the day after.',
					'gatherpress',
				) }
			/>
		</>
	);
};

export default DoorsTime;
