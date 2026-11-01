/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	DropdownMenu,
	Flex,
	FlexBlock,
	FlexItem,
	TextControl,
} from '@wordpress/components';
import {
	chevronDown,
	chevronUp,
	dragHandle,
	moreVertical,
	plus,
	trash,
} from '@wordpress/icons';
import { useCallback, useEffect, useRef } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	addChecklistItem,
	getChecklistProgress,
	moveChecklistItem,
	moveChecklistItemToIndex,
	parseChecklist,
	removeChecklistItem,
	serializeChecklist,
	updateChecklistItem,
} from './helpers';

/**
 * Focus target that stands for the Add item button rather than a row.
 *
 * A symbol, so no stored row id can ever match it.
 *
 * @since TBD
 *
 * @type {symbol}
 */
const ADD_ITEM_FOCUS = Symbol( 'add-item' );

/**
 * The checklist list rendered inside the "Checklist" panel.
 *
 * Rows reorder by dragging the drag handle, which is the mover pattern the
 * block editor uses elsewhere. The same moves stay reachable from the keyboard
 * through each row's options menu, so reordering is not drag-only.
 *
 * @since TBD
 *
 * @return {JSX.Element} The checklist controls.
 */
const ChecklistPanel = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );

	const stored = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'meta' )
				?.gatherpress_checklist,
		[],
	);

	const items = parseChecklist( stored );
	const { completed, total } = getChecklistProgress( items );

	const fieldRefs = useRef( new Map() );
	const addButtonRef = useRef( null );
	const pendingFocus = useRef( null );

	// Adding or removing a row unmounts the control that had focus, so move
	// focus once the changed rows render instead of dropping it to the page body.
	useEffect( () => {
		if ( ! pendingFocus.current ) {
			return;
		}

		const target =
			ADD_ITEM_FOCUS === pendingFocus.current
				? addButtonRef.current
				: fieldRefs.current.get( pendingFocus.current );

		if ( target ) {
			target.focus();
			pendingFocus.current = null;
		}
	} );

	const commit = useCallback(
		( next ) => {
			editPost( {
				meta: { gatherpress_checklist: serializeChecklist( next ) },
			} );
			unlockPostSaving();
		},
		[ editPost, unlockPostSaving ],
	);

	/* translators: 1: Number of completed items, 2: Total number of items. */
	const progressLabel = __( '%1$d of %2$d complete', 'gatherpress' );
	const progress =
		0 === total
			? __( 'Track the steps needed to run this event.', 'gatherpress' )
			: sprintf( progressLabel, completed, total );

	return (
		<>
			<p style={ { marginTop: 0 } }>{ progress }</p>

			{ items.map( ( item, index ) => {
				// New rows start empty, so a blank row falls back to its position
				// to keep the three labels below distinct for screen readers.
				const itemName =
					item.text.trim() ||
					sprintf(
						/* translators: %d: Position of the checklist item. */
						__( 'Item %d', 'gatherpress' ),
						index + 1,
					);
				const completeLabel = sprintf(
					/* translators: %s: The checklist item name. */
					__( 'Mark "%s" complete', 'gatherpress' ),
					itemName,
				);
				const itemLabel = sprintf(
					/* translators: %s: The checklist item name. */
					__( 'Checklist item: %s', 'gatherpress' ),
					itemName,
				);
				const optionsLabel = sprintf(
					/* translators: %s: The checklist item name. */
					__( 'Checklist item options: %s', 'gatherpress' ),
					itemName,
				);

				return (
					<Flex
						key={ item.id }
						gap={ 2 }
						align="flex-start"
						className="gatherpress-checklist-item"
						onDragOver={ ( event ) => event.preventDefault() }
						onDrop={ ( event ) => {
							event.preventDefault();

							const movedId =
								event.dataTransfer?.getData( 'text' );

							if ( movedId && movedId !== item.id ) {
								commit(
									moveChecklistItemToIndex(
										items,
										movedId,
										index,
									),
								);
							}
						} }
					>
						<FlexItem>
							<Button
								className="gatherpress-checklist-item__handle"
								icon={ dragHandle }
								label={ __( 'Drag to reorder', 'gatherpress' ) }
								// Pointer only, like core's block mover. Keyboard users
								// move rows from the options menu.
								tabIndex={ -1 }
								size="compact"
								draggable
								style={ { cursor: 'grab' } }
								onDragStart={ ( event ) =>
									event.dataTransfer?.setData(
										'text',
										item.id,
									)
								}
							/>
						</FlexItem>

						<FlexItem>
							<CheckboxControl
								aria-label={ completeLabel }
								checked={ item.completed }
								onChange={ ( value ) =>
									commit(
										updateChecklistItem( items, item.id, {
											completed: Boolean( value ),
										} ),
									)
								}
							/>
						</FlexItem>

						<FlexBlock>
							<TextControl
								__next40pxDefaultSize
								label={ itemLabel }
								hideLabelFromVision
								ref={ ( node ) => {
									fieldRefs.current.set( item.id, node );
								} }
								value={ item.text }
								onChange={ ( value ) =>
									commit(
										updateChecklistItem( items, item.id, {
											text: value,
										} ),
									)
								}
							/>
						</FlexBlock>

						<FlexItem>
							<DropdownMenu
								icon={ moreVertical }
								label={ optionsLabel }
								controls={ [
									{
										icon: chevronUp,
										title: __( 'Move up', 'gatherpress' ),
										isDisabled: 0 === index,
										onClick: () =>
											commit(
												moveChecklistItem(
													items,
													item.id,
													-1,
												),
											),
									},
									{
										icon: chevronDown,
										title: __(
											'Move down',
											'gatherpress',
										),
										isDisabled: index === total - 1,
										onClick: () =>
											commit(
												moveChecklistItem(
													items,
													item.id,
													1,
												),
											),
									},
									{
										icon: trash,
										title: __(
											'Remove item',
											'gatherpress',
										),
										onClick: () => {
											// Next row, else the previous one, else Add item.
											const neighbor =
												items[ index + 1 ] ??
												items[ index - 1 ];

											pendingFocus.current =
												neighbor?.id ?? ADD_ITEM_FOCUS;
											commit(
												removeChecklistItem(
													items,
													item.id,
												),
											);
										},
									},
								] }
							/>
						</FlexItem>
					</Flex>
				);
			} ) }

			<Button
				ref={ addButtonRef }
				variant="secondary"
				icon={ plus }
				onClick={ () => {
					const next = addChecklistItem( items );

					// At the item cap no row is added, so there is nothing to focus.
					if ( next !== items ) {
						pendingFocus.current = next.at( -1 ).id;
					}

					commit( next );
				} }
			>
				{ __( 'Add item', 'gatherpress' ) }
			</Button>
		</>
	);
};

export default ChecklistPanel;
