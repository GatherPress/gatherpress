/**
 * External dependencies.
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';

jest.mock( '@wordpress/blocks', () => ( {
	createBlock: jest.fn( ( name, attributes = {}, innerBlocks = [] ) => ( {
		name,
		attributes,
		innerBlocks,
	} ) ),
	cloneBlock: jest.fn( ( block, attributes, innerBlocks ) => ( {
		...block,
		...( innerBlocks ? { innerBlocks } : {} ),
		isClone: true,
	} ) ),
} ) );

/**
 * WordPress dependencies.
 */
import { cloneBlock, createBlock } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import transforms from '@src/blocks/calendar/transforms';
import {
	DAY_NUMBER,
	ENTRY_MODAL_MANAGER_ATTRIBUTES,
	ENTRY_START_TIME,
	ENTRY_TITLE_TRIGGER,
} from '@src/blocks/calendar/constants';

/**
 * Build a block in the shape that the createBlock mock returns.
 *
 * @param {string} name        Block name.
 * @param {Object} attributes  Block attributes.
 * @param {Array}  innerBlocks Inner blocks.
 *
 * @return {Object} Block object.
 */
const block = ( name, attributes = {}, innerBlocks = [] ) => ( {
	name,
	attributes,
	innerBlocks,
} );

/**
 * Get the names of a list of blocks.
 *
 * @param {Array} blocks Blocks.
 *
 * @return {Array} Block names, in order.
 */
const names = ( blocks ) => blocks.map( ( { name } ) => name );

/**
 * Build the Close button group that the calendar puts in its modal content.
 *
 * @return {Object} A core/buttons block holding the Close button.
 */
const closeButtons = () =>
	block( 'core/buttons', { align: 'center' }, [
		block( 'core/button', {
			tagName: 'button',
			className: 'gatherpress-modal--trigger-close',
			text: 'Close',
		} ),
	] );

/**
 * Build the inner blocks of a gatherpress/calendar block.
 *
 * @param {Array} modalContentBlocks Inner blocks of gatherpress/modal-content.
 *
 * @return {Array} The calendar's inner blocks (one week).
 */
const calendarInnerBlocks = ( modalContentBlocks ) => [
	block( 'gatherpress/calendar-week', {}, [
		block( 'gatherpress/calendar-day', {}, [
			block( ...DAY_NUMBER ),
			block( 'gatherpress/calendar-entries', {}, [
				block(
					'gatherpress/modal-manager',
					ENTRY_MODAL_MANAGER_ATTRIBUTES,
					[
						block( ...ENTRY_START_TIME ),
						block( ...ENTRY_TITLE_TRIGGER ),
						block( 'gatherpress/modal', {}, [
							block(
								'gatherpress/modal-content',
								{},
								modalContentBlocks,
							),
						] ),
					],
				),
			] ),
		] ),
	] ),
];

/**
 * Walk from a calendar block to the blocks of its first day.
 *
 * @param {Object} calendar A gatherpress/calendar block.
 *
 * @return {Object} The day, entries, Modal Manager, modal and modal content.
 */
const unwrap = ( calendar ) => {
	const [ week ] = calendar.innerBlocks;
	const [ day ] = week.innerBlocks;
	const [ dayNumber, entries ] = day.innerBlocks;
	const [ modalManager ] = entries.innerBlocks;
	const [ startTime, trigger, modal ] = modalManager.innerBlocks;
	const [ modalContent ] = modal.innerBlocks;

	return {
		week,
		day,
		dayNumber,
		entries,
		modalManager,
		startTime,
		trigger,
		modal,
		modalContent,
	};
};

describe( 'gatherpress/calendar transforms', () => {
	const [ fromPostTemplate ] = transforms.from;
	const [ toPostTemplate ] = transforms.to;

	const postTitle = block( 'core/post-title', { isLink: true } );
	const postExcerpt = block( 'core/post-excerpt', { moreText: 'More' } );

	beforeEach( () => {
		createBlock.mockClear();
		cloneBlock.mockClear();
	} );

	describe( 'from core/post-template', () => {
		it( 'declares a single block transform from core/post-template', () => {
			expect( transforms.from ).toHaveLength( 1 );
			expect( fromPostTemplate.type ).toBe( 'block' );
			expect( fromPostTemplate.blocks ).toEqual( [
				'core/post-template',
			] );
		} );

		it( 'builds the full calendar hierarchy around the post template blocks', () => {
			const calendar = fromPostTemplate.transform( {}, [
				postTitle,
				postExcerpt,
			] );
			const parts = unwrap( calendar );

			expect( calendar.name ).toBe( 'gatherpress/calendar' );
			expect( calendar.attributes ).toEqual( {} );
			expect( names( calendar.innerBlocks ) ).toEqual( [
				'gatherpress/calendar-week',
			] );
			expect( names( parts.week.innerBlocks ) ).toEqual( [
				'gatherpress/calendar-day',
			] );
			expect( names( parts.day.innerBlocks ) ).toEqual( [
				'core/paragraph',
				'gatherpress/calendar-entries',
			] );
			expect( names( parts.entries.innerBlocks ) ).toEqual( [
				'gatherpress/modal-manager',
			] );
			expect( names( parts.modalManager.innerBlocks ) ).toEqual( [
				'gatherpress/event-date',
				'core/post-title',
				'gatherpress/modal',
			] );
			expect( names( parts.modal.innerBlocks ) ).toEqual( [
				'gatherpress/modal-content',
			] );
			expect( names( parts.modalContent.innerBlocks ) ).toEqual( [
				'core/post-title',
				'core/post-excerpt',
				'core/buttons',
			] );
		} );

		it( 'uses the shared day number, start time, title trigger and Modal Manager settings', () => {
			const parts = unwrap(
				fromPostTemplate.transform( {}, [ postTitle ] ),
			);

			expect( parts.dayNumber.attributes ).toEqual( DAY_NUMBER[ 1 ] );
			expect(
				parts.dayNumber.attributes.metadata.bindings.content.source,
			).toBe( 'gatherpress/calendar-day' );
			expect( parts.startTime.attributes ).toEqual(
				ENTRY_START_TIME[ 1 ],
			);
			expect( parts.trigger.attributes ).toEqual(
				ENTRY_TITLE_TRIGGER[ 1 ],
			);
			expect( parts.trigger.attributes.className ).toBe(
				'gatherpress-modal--trigger-open',
			);
			expect( parts.modalManager.attributes ).toEqual(
				ENTRY_MODAL_MANAGER_ATTRIBUTES,
			);
		} );

		it( 'moves a copy of each post template block into the modal content, in order', () => {
			const parts = unwrap(
				fromPostTemplate.transform( {}, [ postTitle, postExcerpt ] ),
			);
			const [ first, second ] = parts.modalContent.innerBlocks;

			expect( cloneBlock ).toHaveBeenCalledTimes( 2 );
			expect( cloneBlock.mock.calls[ 0 ][ 0 ] ).toBe( postTitle );
			expect( cloneBlock.mock.calls[ 1 ][ 0 ] ).toBe( postExcerpt );
			expect( first ).not.toBe( postTitle );
			expect( first ).toEqual( { ...postTitle, isClone: true } );
			expect( second ).toEqual( { ...postExcerpt, isClone: true } );
		} );

		it( 'ends the modal content with a centered Close button that closes the modal', () => {
			const parts = unwrap(
				fromPostTemplate.transform( {}, [ postTitle ] ),
			);
			const buttons = parts.modalContent.innerBlocks.at( -1 );

			expect( buttons.name ).toBe( 'core/buttons' );
			expect( buttons.attributes ).toEqual( {
				align: 'center',
				layout: { type: 'flex', justifyContent: 'center' },
			} );
			expect( buttons.innerBlocks ).toEqual( [
				block( 'core/button', {
					tagName: 'button',
					className: 'gatherpress-modal--trigger-close',
					text: 'Close',
				} ),
			] );
		} );

		it( 'styles the modal content and the calendar entries', () => {
			const parts = unwrap(
				fromPostTemplate.transform( {}, [ postTitle ] ),
			);

			expect( parts.modalContent.attributes.backgroundColor ).toBe(
				'base',
			);
			expect(
				parts.modalContent.attributes.style.dimensions.maxWidth,
			).toBe( '400px' );
			expect( parts.entries.attributes.className ).toBe(
				'is-style-default',
			);
			expect( parts.entries.attributes.style.spacing.blockGap ).toBe(
				'0',
			);
			expect(
				parts.entries.attributes.style.layout.selfStretch,
			).toBe( 'fill' );
		} );

		it( 'puts only the Close button in the modal content when the post template is empty', () => {
			const parts = unwrap( fromPostTemplate.transform( {}, [] ) );

			expect( cloneBlock ).not.toHaveBeenCalled();
			expect( names( parts.modalContent.innerBlocks ) ).toEqual( [
				'core/buttons',
			] );
		} );

		it( 'puts only the Close button in the modal content when no inner blocks are passed', () => {
			const parts = unwrap( fromPostTemplate.transform( {} ) );

			expect( cloneBlock ).not.toHaveBeenCalled();
			expect( names( parts.modalContent.innerBlocks ) ).toEqual( [
				'core/buttons',
			] );
		} );
	} );

	describe( 'to core/post-template', () => {
		it( 'declares a single block transform to core/post-template', () => {
			expect( transforms.to ).toHaveLength( 1 );
			expect( toPostTemplate.type ).toBe( 'block' );
			expect( toPostTemplate.blocks ).toEqual( [ 'core/post-template' ] );
		} );

		it( 'moves copies of the modal content blocks into a post template and drops the Close button', () => {
			const template = toPostTemplate.transform(
				{},
				calendarInnerBlocks( [
					postTitle,
					postExcerpt,
					closeButtons(),
				] ),
			);

			expect( template.name ).toBe( 'core/post-template' );
			expect( template.attributes ).toEqual( {} );
			expect( template.innerBlocks ).toEqual( [
				{ ...postTitle, isClone: true },
				{ ...postExcerpt, isClone: true },
			] );
			expect( template.innerBlocks[ 0 ] ).not.toBe( postTitle );
		} );

		it( 'keeps a buttons group whose buttons do not close the modal', () => {
			const register = block( 'core/buttons', {}, [
				block( 'core/button', { className: 'is-style-outline' } ),
				block( 'core/button', { text: 'No class' } ),
				{ name: 'core/button' },
			] );
			const empty = { name: 'core/buttons', attributes: {} };

			const template = toPostTemplate.transform(
				{},
				calendarInnerBlocks( [ register, empty, closeButtons() ] ),
			);

			expect( template.innerBlocks ).toEqual( [
				{ ...register, isClone: true },
				{ ...empty, isClone: true },
			] );
		} );

		it( 'keeps a block that is not a buttons group even when it holds a Close button', () => {
			const group = block( 'core/group', {}, [ closeButtons() ] );

			const template = toPostTemplate.transform(
				{},
				calendarInnerBlocks( [ group ] ),
			);

			expect( template.innerBlocks ).toEqual( [
				{ ...group, isClone: true },
			] );
		} );

		it( 'removes only the Close button from a buttons group that holds other buttons', () => {
			const register = block( 'core/button', {
				text: 'Register',
				url: '/register',
			} );
			const group = closeButtons();
			group.innerBlocks.unshift( register );

			const template = toPostTemplate.transform(
				{},
				calendarInnerBlocks( [ postTitle, group ] ),
			);

			expect( template.innerBlocks ).toEqual( [
				{ ...postTitle, isClone: true },
				{
					...group,
					innerBlocks: [ { ...register, isClone: true } ],
					isClone: true,
				},
			] );
		} );

		it( 'falls back to a linked post title and the excerpt when the modal content holds only the Close button', () => {
			const template = toPostTemplate.transform(
				{},
				calendarInnerBlocks( [ closeButtons() ] ),
			);

			expect( template.innerBlocks ).toEqual( [
				block( 'core/post-title', { isLink: true } ),
				block( 'core/post-excerpt' ),
			] );
		} );

		it( 'uses copies of the calendar entries blocks when there is no modal content', () => {
			const title = block( 'core/post-title', { level: 4 } );
			const calendar = [
				block( 'gatherpress/calendar-week', {}, [
					block( 'gatherpress/calendar-day', {}, [
						block( 'gatherpress/calendar-entries', {}, [ title ] ),
					] ),
				] ),
			];

			const template = toPostTemplate.transform( {}, calendar );

			expect( template.innerBlocks ).toEqual( [
				{ ...title, isClone: true },
			] );
		} );

		it( 'falls back to a linked post title and the excerpt when the modal content is empty, not to a copy of the Modal Manager', () => {
			const template = toPostTemplate.transform(
				{},
				calendarInnerBlocks( [] ),
			);

			expect( cloneBlock ).not.toHaveBeenCalled();
			expect( template.innerBlocks ).toEqual( [
				block( 'core/post-title', { isLink: true } ),
				block( 'core/post-excerpt' ),
			] );
		} );

		it( 'falls back to a linked post title and the excerpt when the modal content has no inner blocks list', () => {
			const calendar = [
				block( 'gatherpress/calendar-entries', {}, [
					block( 'core/post-title' ),
					{ name: 'gatherpress/modal-content', attributes: {} },
				] ),
			];

			const template = toPostTemplate.transform( {}, calendar );

			expect( names( template.innerBlocks ) ).toEqual( [
				'core/post-title',
				'core/post-excerpt',
			] );
			expect( template.innerBlocks[ 0 ].attributes ).toEqual( {
				isLink: true,
			} );
		} );

		it( 'falls back to a linked post title and the excerpt when the calendar has no entries', () => {
			const calendar = [
				block( 'gatherpress/calendar-week', {}, [
					block( 'gatherpress/calendar-day', {}, [
						block( ...DAY_NUMBER ),
					] ),
				] ),
			];

			const template = toPostTemplate.transform( {}, calendar );

			expect( cloneBlock ).not.toHaveBeenCalled();
			expect( template.innerBlocks ).toEqual( [
				block( 'core/post-title', { isLink: true } ),
				block( 'core/post-excerpt' ),
			] );
		} );

		it.each( [
			[ 'an empty list', block( 'gatherpress/calendar-entries' ) ],
			[
				'no list',
				{ name: 'gatherpress/calendar-entries', attributes: {} },
			],
		] )(
			'falls back to a linked post title and the excerpt when the calendar entries have %s of inner blocks',
			( label, entries ) => {
				const template = toPostTemplate.transform( {}, [ entries ] );

				expect( names( template.innerBlocks ) ).toEqual( [
					'core/post-title',
					'core/post-excerpt',
				] );
			},
		);

		it( 'falls back to a linked post title and the excerpt when no inner blocks are passed', () => {
			const template = toPostTemplate.transform( {} );

			expect( template.name ).toBe( 'core/post-template' );
			expect( template.innerBlocks ).toEqual( [
				block( 'core/post-title', { isLink: true } ),
				block( 'core/post-excerpt' ),
			] );
		} );
	} );

	it( 'gives back the original post template blocks after a transform to the calendar and back', () => {
		const calendar = fromPostTemplate.transform( {}, [
			postTitle,
			postExcerpt,
		] );
		const template = toPostTemplate.transform( {}, calendar.innerBlocks );

		expect( names( template.innerBlocks ) ).toEqual( [
			'core/post-title',
			'core/post-excerpt',
		] );
		expect( template.innerBlocks[ 0 ].attributes ).toEqual(
			postTitle.attributes,
		);
		expect( template.innerBlocks[ 1 ].attributes ).toEqual(
			postExcerpt.attributes,
		);
	} );
} );
