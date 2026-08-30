/**
 * WordPress dependencies
 */
import {
	Button,
	CheckboxControl,
	Dropdown,
	MenuGroup,
} from '@wordpress/components';
import { funnel } from '@wordpress/icons';
import { __, sprintf, _n } from '@wordpress/i18n';

/**
 * Builds the toggle's accessible name.
 *
 * The control is an icon, so this is its announced name and its tooltip. It
 * names the current selection, which is otherwise invisible.
 *
 * @since 0.36.0
 *
 * @param {Object[]} statuses Every selectable status.
 * @param {string[]} selected Currently selected status values.
 *
 * @return {string} The button label.
 */
export function getResponseLabel( statuses, selected ) {
	if ( ! selected.length ) {
		return __( 'Filter by response: all', 'gatherpress' );
	}

	if ( 1 === selected.length ) {
		const match = statuses.find( ( s ) => s.value === selected[ 0 ] );

		return match
			? sprintf(
				/* translators: %s: an RSVP response, e.g. "Waiting List". */
				__( 'Filter by response: %s', 'gatherpress' ),
				match.label
			)
			: __( 'Filter by response', 'gatherpress' );
	}

	return sprintf(
		/* translators: %d: number of selected RSVP responses. */
		_n(
			'Filter by response: %d selected',
			'Filter by response: %d selected',
			selected.length,
			'gatherpress'
		),
		selected.length
	);
}

/**
 * Adds or removes one status from a selection.
 *
 * @since 0.36.0
 *
 * @param {string[]} selected Current selection.
 * @param {string}   value    The status being toggled.
 *
 * @return {string[]} The new selection.
 */
export function toggleResponse( selected, value ) {
	return selected.includes( value )
		? selected.filter( ( item ) => item !== value )
		: [ ...selected, value ];
}

/**
 * The RSVP screen's response filter.
 *
 * A checkbox dropdown rather than a row of toggles, so the tablenav keeps a
 * constant width. Selecting nothing means every response.
 *
 * @since 0.36.0
 *
 * @param {Object}   props          Component props.
 * @param {Object[]} props.statuses Selectable statuses, each `{ value, label }`.
 * @param {string[]} props.selected Currently selected status values.
 * @param {Function} props.onChange Called with the new selection.
 *
 * @return {JSX.Element} The filter control.
 */
export default function ResponseFilter( { statuses, selected, onChange } ) {
	return (
		<div className="gatherpress-rsvp-response-filter">
			<Dropdown
				className="gatherpress-rsvp-response-filter__dropdown"
				popoverProps={ {
					className: 'gatherpress-rsvp-response-filter__popover',
					placement: 'bottom-start',
					// The fade needs the editor's motion context, and without
					// it the popover sticks at opacity 0.266.
					animate: false,
					// Portaled to the body, the last checkbox ends the page's
					// tab order; inline, tab continues to the Filter button.
					inline: true,
				} }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						variant="secondary"
						icon={ funnel }
						label={ getResponseLabel( statuses, selected ) }
						showTooltip
						onClick={ onToggle }
						aria-expanded={ isOpen }
						// An icon alone cannot show a filter is active.
						text={
							selected.length ? String( selected.length ) : undefined
						}
					/>
				) }
				renderContent={ () => (
					<MenuGroup label={ __( 'Responses', 'gatherpress' ) }>
						{ statuses.map( ( status ) => (
							<CheckboxControl
								key={ status.value }
								label={ status.label }
								checked={ selected.includes( status.value ) }
								onChange={ () =>
									onChange(
										toggleResponse( selected, status.value )
									)
								}
							/>
						) ) }
					</MenuGroup>
				) }
			/>
		</div>
	);
}
