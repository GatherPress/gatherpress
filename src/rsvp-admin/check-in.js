/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { speak } from '@wordpress/a11y';
import { Button, Dropdown, MenuGroup, MenuItem } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Shows the SVG icon of a check-in state.
 *
 * The server escapes the markup with `Check_In_Registry::kses_icon()`.
 *
 * @since TBD
 *
 * @param {Object} props      Component props.
 * @param {string} props.html The icon's SVG markup.
 *
 * @return {JSX.Element} The icon.
 */
function StateIcon( { html } ) {
	return (
		<span
			className="gatherpress-rsvp-check-in__icon"
			dangerouslySetInnerHTML={ { __html: html } }
		/>
	);
}

/**
 * The check-in dropdown of one RSVP on the RSVP screen.
 *
 * Shows the RSVP's check-in state and sets another one in place, through the
 * check-in REST route.
 *
 * @since TBD
 *
 * @param {Object}   props              Component props.
 * @param {number}   props.rsvpId       The RSVP comment ID.
 * @param {string}   props.initialState The state the page loaded with.
 * @param {Object[]} props.states       Every state, each `{ value, label, icon }`, not recorded last.
 *
 * @return {JSX.Element} The dropdown.
 */
export default function CheckIn( { rsvpId, initialState, states } ) {
	const [ state, setState ] = useState( initialState );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const current =
		states.find( ( option ) => option.value === state ) ?? states.at( -1 );

	/**
	 * Saves a new check-in state.
	 *
	 * @param {string} value The state to save.
	 *
	 * @return {Promise<void>} Resolves once the save settles.
	 */
	const save = async ( value ) => {
		setIsSaving( true );
		setError( '' );

		try {
			const response = await apiFetch( {
				path: `/gatherpress/v1/rsvp/${ rsvpId }/check-in`,
				method: 'POST',
				data: { state: value },
			} );

			setState( response.value );
			speak(
				sprintf(
					/* translators: %s: a check-in state, e.g. "Checked in". */
					__( 'Check-in saved: %s', 'gatherpress' ),
					response.label,
				),
			);
		} catch ( saveError ) {
			setError(
				saveError?.message ||
					__( 'The check-in could not be saved.', 'gatherpress' ),
			);
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<>
			<Dropdown
				className="gatherpress-rsvp-check-in__dropdown"
				popoverProps={ {
					placement: 'bottom-start',
					// As in the response filter: without the editor's motion
					// context the fade sticks partway.
					animate: false,
					inline: true,
				} }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						variant="tertiary"
						size="compact"
						icon={ <StateIcon html={ current?.icon ?? '' } /> }
						onClick={ onToggle }
						aria-expanded={ isOpen }
						aria-label={ sprintf(
							/* translators: %s: a check-in state, e.g. "Checked in". */
							__( 'Check-in: %s', 'gatherpress' ),
							current?.label ?? '',
						) }
						isBusy={ isSaving }
						disabled={ isSaving }
						accessibleWhenDisabled
					>
						{ current?.label }
					</Button>
				) }
				renderContent={ ( { onClose } ) => (
					<MenuGroup label={ __( 'Check-in', 'gatherpress' ) }>
						{ states.map( ( option ) => (
							<MenuItem
								key={ option.value }
								icon={ <StateIcon html={ option.icon } /> }
								iconPosition="left"
								role="menuitemradio"
								isSelected={ option.value === state }
								onClick={ () => {
									onClose();

									if ( option.value !== state ) {
										save( option.value );
									}
								} }
							>
								{ option.label }
							</MenuItem>
						) ) }
					</MenuGroup>
				) }
			/>
			{ error && (
				<span className="gatherpress-rsvp-check-in__error" role="alert">
					{ error }
				</span>
			) }
		</>
	);
}
