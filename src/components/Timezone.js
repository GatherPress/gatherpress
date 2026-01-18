/**
 * WordPress dependencies.
 */
import { PanelRow, SelectControl } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../helpers/globals';
import { enableSave } from '../helpers/editor';
import {
	maybeConvertUtcOffsetForDatabase,
	maybeConvertUtcOffsetForSelect,
} from '../helpers/datetime';

/**
 * Timezone component for GatherPress.
 *
 * This component allows users to select their preferred time zone from a list of choices.
 * It includes a SelectControl with options grouped by regions. The selected time zone is
 * stored in the state and updated via the setTimezone function.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const Timezone = () => {
	const { timezone } = useSelect(
		( select ) => ( {
			timezone: select( 'gatherpress/datetime' ).getTimezone(),
		} ),
		[],
	);
	const { setTimezone } = useDispatch( 'gatherpress/datetime' );
	const choices = getFromGlobal( 'misc.timezoneChoices' );

	// Run only once.
	useEffect( () => {
		setTimezone( getFromGlobal( 'eventDetails.dateTime.timezone' ) );
	}, [ setTimezone ] );

	return (
		<PanelRow>
			<SelectControl
				label={ __( 'Time Zone', 'gatherpress' ) }
				value={ maybeConvertUtcOffsetForSelect( timezone ) }
				onChange={ ( value ) => {
					value = maybeConvertUtcOffsetForDatabase( value );
					setTimezone( value );
					enableSave();
				} }
				__nexthasnomarginbottom
			>
				{ choices &&
				'object' === typeof choices &&
				0 < Object.keys( choices ).length ? (
						Object.keys( choices ).map( ( group ) => {
							return (
								<optgroup key={ group } label={ group }>
									{ Object.keys( choices[ group ] ).map( ( item ) => {
										return (
											<option key={ item } value={ item }>
												{ choices[ group ][ item ] }
											</option>
										);
									} ) }
								</optgroup>
							);
						} )
					) : (
						<option value="">
							{ __( 'Error, no choices available', 'gatherpress' ) }
						</option>
					) }
			</SelectControl>
		</PanelRow>
	);
};

export default Timezone;
