/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Flex,
	FlexBlock,
	FlexItem,
	TextControl,
} from '@wordpress/components';
import { chevronDown, chevronUp, plus, trash } from '@wordpress/icons';
import { useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { usePostTypeSupports } from '../../../helpers/event';
import {
	addChecklistItem,
	getChecklistProgress,
	moveChecklistItem,
	parseChecklist,
	removeChecklistItem,
	serializeChecklist,
	updateChecklistItem,
} from './helpers';

/**
 * A panel for the event's organizer checklist.
 *
 * The checklist is shared working state for the people running an event: who
 * has been contacted, whose compliance review is done, which invoice is still
 * outstanding, and so on. It is stored as JSON in the `gatherpress_checklist`
 * post meta, so it saves with the event and is only readable by users who can
 * edit that event.
 *
 * Gated on the `gatherpress-event-checklist` support through the reactive
 * hook: supports come out of the post type registry, which is not cached on
 * the editor's first render, so a non-reactive read would leave the panel
 * permanently hidden on every post type.
 *
 * @since 0.36.0
 *
 * @return {JSX.Element | null} The checklist panel, or null for post types
 *                              without checklist support.
 */
const ChecklistPanel = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );
	const supportsChecklist = usePostTypeSupports(
		'gatherpress-event-checklist'
	);

	const stored = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'meta' )
				?.gatherpress_checklist,
		[]
	);

	const items = parseChecklist( stored );
	const { completed, total } = getChecklistProgress( items );

	const commit = useCallback(
		( next ) => {
			editPost( {
				meta: { gatherpress_checklist: serializeChecklist( next ) },
			} );
			unlockPostSaving();
		},
		[ editPost, unlockPostSaving ]
	);

	if ( ! supportsChecklist ) {
		return null;
	}

	/* translators: 1: Number of completed items, 2: Total number of items. */
	const progressLabel = __( '%1$d of %2$d complete', 'gatherpress' );
	const progress =
		0 === total
			? __( 'Track the steps needed to run this event.', 'gatherpress' )
			: sprintf( progressLabel, completed, total );

	return (
		<section>
			<h3 style={ { marginBottom: '0.5rem' } }>
				{ __( 'Checklist', 'gatherpress' ) }
			</h3>

			<p style={ { marginTop: 0 } }>{ progress }</p>

			{ items.map( ( item, index ) => {
				const completeLabel = sprintf(
					/* translators: %s: The checklist item text. */
					__( 'Mark "%s" complete', 'gatherpress' ),
					item.text
				);
				const itemLabel = sprintf(
					/* translators: %s: The checklist item text. */
					__( 'Checklist item: %s', 'gatherpress' ),
					item.text
				);

				return (
					<Flex key={ item.id } gap={ 2 } align="flex-start">
						<FlexItem>
							<CheckboxControl
								aria-label={ completeLabel }
								checked={ item.completed }
								onChange={ ( value ) =>
									commit(
										updateChecklistItem( items, item.id, {
											completed: Boolean( value ),
										} )
									)
								}
							/>
						</FlexItem>

						<FlexBlock>
							<TextControl
								__next40pxDefaultSize
								label={ itemLabel }
								hideLabelFromVision
								value={ item.text }
								onChange={ ( value ) =>
									commit(
										updateChecklistItem( items, item.id, {
											text: value,
										} )
									)
								}
							/>
						</FlexBlock>

						<FlexItem>
							<Button
								icon={ chevronUp }
								label={ __( 'Move up', 'gatherpress' ) }
								disabled={ 0 === index }
								onClick={ () =>
									commit(
										moveChecklistItem( items, item.id, -1 )
									)
								}
							/>
							<Button
								icon={ chevronDown }
								label={ __( 'Move down', 'gatherpress' ) }
								disabled={ index === total - 1 }
								onClick={ () =>
									commit( moveChecklistItem( items, item.id, 1 ) )
								}
							/>
							<Button
								icon={ trash }
								label={ __( 'Remove item', 'gatherpress' ) }
								onClick={ () =>
									commit( removeChecklistItem( items, item.id ) )
								}
							/>
						</FlexItem>
					</Flex>
				);
			} ) }

			<Button
				variant="secondary"
				icon={ plus }
				onClick={ () => commit( addChecklistItem( items ) ) }
			>
				{ __( 'Add item', 'gatherpress' ) }
			</Button>
		</section>
	);
};

export default ChecklistPanel;
