/**
 * WordPress dependencies
 */
import { createBlock, cloneBlock } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	DAY_NUMBER,
	ENTRY_MODAL_MANAGER_ATTRIBUTES,
	ENTRY_START_TIME,
	ENTRY_TITLE_TRIGGER,
} from './constants';
import { findBlockByName } from './utils/block-sync-utils';

/**
 * Wraps content blocks inside the complete calendar template hierarchy.
 *
 * @param {Array} contentBlocks Blocks to preserve inside gatherpress/modal-content.
 * @return {Object} New gatherpress/calendar block.
 */
function createCalendarFromTemplate( contentBlocks = [] ) {
	// 1. Prepare preserved blocks inside modal content with a Close button
	const modalContentBlocks = [
		...contentBlocks.map( ( block ) => cloneBlock( block ) ),
		createBlock(
			'core/buttons',
			{
				align: 'center',
				layout: { type: 'flex', justifyContent: 'center' },
			},
			[
				createBlock( 'core/button', {
					tagName: 'button',
					className: 'gatherpress-modal--trigger-close',
					text: __( 'Close', 'gatherpress' ),
				} ),
			],
		),
	];

	const modalContent = createBlock(
		'gatherpress/modal-content',
		{
			style: {
				dimensions: { maxWidth: '400px' },
				spacing: {
					padding: {
						top: 'var:preset|spacing|30',
						bottom: 'var:preset|spacing|30',
						left: 'var:preset|spacing|30',
						right: 'var:preset|spacing|30',
					},
				},
			},
			backgroundColor: 'base',
		},
		modalContentBlocks,
	);

	const modal = createBlock( 'gatherpress/modal', {}, [ modalContent ] );

	// Same blocks as ENTRIES_TEMPLATE; the linked title opens the modal.
	const startTime = createBlock( ...ENTRY_START_TIME );
	const trigger = createBlock( ...ENTRY_TITLE_TRIGGER );

	const modalManager = createBlock(
		'gatherpress/modal-manager',
		ENTRY_MODAL_MANAGER_ATTRIBUTES,
		[ startTime, trigger, modal ],
	);

	const entries = createBlock(
		'gatherpress/calendar-entries',
		{
			layout: { type: 'default' },
			className: 'is-style-default',
			style: {
				spacing: {
					blockGap: '0',
					padding: { top: '0', bottom: '0', left: '0', right: '0' },
					margin: { top: '0', bottom: '0', left: '0', right: '0' },
				},
				layout: {
					selfStretch: 'fill',
					flexSize: null,
				},
			},
		},
		[ modalManager ],
	);

	// Same paragraph as DAY_TEMPLATE, with the class that block styles use.
	const dayNumber = createBlock( ...DAY_NUMBER );

	const day = createBlock( 'gatherpress/calendar-day', {}, [
		dayNumber,
		entries,
	] );

	const week = createBlock( 'gatherpress/calendar-week', {}, [ day ] );

	return createBlock( 'gatherpress/calendar', {}, [ week ] );
}

/**
 * Extracts preserved blocks from modal-content inside the calendar hierarchy.
 *
 * @param {Array} calendarInnerBlocks The gatherpress/calendar innerBlocks.
 * @return {Array} Blocks to place in core/post-template.
 */
function extractModalContentBlocks( calendarInnerBlocks = [] ) {
	const modalContent = findBlockByName(
		calendarInnerBlocks,
		'gatherpress/modal-content',
	);

	if ( ! modalContent || ! modalContent.innerBlocks?.length ) {
		// Fallback: look for calendar-entries or return default template blocks
		const entries = findBlockByName(
			calendarInnerBlocks,
			'gatherpress/calendar-entries',
		);
		if ( entries?.innerBlocks?.length ) {
			return entries.innerBlocks.map( ( block ) => cloneBlock( block ) );
		}
		return [
			createBlock( 'core/post-title', { isLink: true } ),
			createBlock( 'core/post-excerpt' ),
		];
	}

	// Filter out the modal's Close button since it is no longer inside a modal
	const preservedBlocks = modalContent.innerBlocks
		.filter( ( block ) => {
			const isCloseButtons =
				'core/buttons' === block.name &&
				block.innerBlocks?.some( ( btn ) =>
					btn.attributes?.className?.includes(
						'gatherpress-modal--trigger-close',
					),
				);
			return ! isCloseButtons;
		} )
		.map( ( block ) => cloneBlock( block ) );

	return preservedBlocks.length
		? preservedBlocks
		: modalContent.innerBlocks.map( ( block ) => cloneBlock( block ) );
}
/**
 * Transforming core/post-template ➔ gatherpress/calendar:
 *
 * Clicking the block switcher in the toolbar transforms the list into a calendar.
 * The Post Title, Excerpt, Event Date, and custom blocks are moved inside the popover gatherpress/modal-content.
 *
 * Transforming gatherpress/calendar ➔ core/post-template:
 *
 * Clicking the block switcher transforms the calendar back into a standard core/post-template.
 * All blocks configured inside gatherpress/modal-content are extracted
 * and placed directly in the template loop (with the modal close button cleanly stripped).
 */
const transforms = {
	from: [
		{
			type: 'block',
			blocks: [ 'core/post-template' ],
			transform: ( attributes, innerBlocks ) => {
				return createCalendarFromTemplate( innerBlocks );
			},
		},
	],
	to: [
		{
			type: 'block',
			blocks: [ 'core/post-template' ],
			transform: ( attributes, innerBlocks ) => {
				const preservedBlocks =
					extractModalContentBlocks( innerBlocks );
				return createBlock( 'core/post-template', {}, preservedBlocks );
			},
		},
	],
};

export default transforms;
