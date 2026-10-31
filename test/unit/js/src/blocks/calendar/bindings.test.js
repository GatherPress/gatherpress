/**
 * External dependencies.
 */
import {
	afterEach,
	beforeEach,
	describe,
	expect,
	it,
	jest,
} from '@jest/globals';
import '@testing-library/jest-dom';
import { fireEvent, render, screen } from '@testing-library/react';

jest.mock( '@wordpress/blocks', () => ( {
	registerBlockBindingsSource: jest.fn(),
} ) );

jest.mock( '@wordpress/hooks', () => ( {
	addFilter: jest.fn(),
} ) );

// Run the domReady callback at once, so the import registers the sources.
jest.mock( '@wordpress/dom-ready', () => ( callback ) => callback() );

jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
} ) );

jest.mock( '@wordpress/core-data', () => ( {
	store: 'core',
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	InspectorControls: ( { children } ) => <div>{ children }</div>,
} ) );

jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { title, children } ) => (
		<section aria-label={ title }>{ children }</section>
	),
	SelectControl: ( { label, value, options, onChange } ) => (
		<select
			aria-label={ label }
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
		>
			{ options.map( ( option ) => (
				<option key={ option.value } value={ option.value }>
					{ option.label }
				</option>
			) ) }
		</select>
	),
} ) );

// The real heading goes through dateI18n in the site time zone. The test
// reads the range from the local date parts, so it works in every zone.
jest.mock( '@src/blocks/calendar/utils/date-utils', () => {
	const actual = jest.requireActual( '@src/blocks/calendar/utils/date-utils' );

	return {
		...actual,
		formatHeading: jest.fn(
			( viewType, startDate, endDate ) =>
				`${ viewType } ${ actual.formatDate(
					startDate,
				) } to ${ actual.formatDate( endDate ) }`,
		),
	};
} );

/**
 * WordPress dependencies.
 */
import { registerBlockBindingsSource } from '@wordpress/blocks';
import { select as wpSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies.
 */
import '@src/blocks/calendar/bindings';

const sources = Object.fromEntries(
	registerBlockBindingsSource.mock.calls.map( ( [ source ] ) => [
		source.name,
		source,
	] ),
);
const [ , , withDayNumberBindingControls ] = addFilter.mock.calls[ 0 ];

/**
 * Build a calendar block.
 *
 * @param {string} clientId   Block client ID.
 * @param {Object} attributes Calendar attributes.
 *
 * @return {Object} Block.
 */
const calendar = ( clientId, attributes ) => ( {
	clientId,
	name: 'gatherpress/calendar',
	attributes,
	innerBlocks: [],
} );

/**
 * Build a block that holds other blocks.
 *
 * @param {string} clientId    Block client ID.
 * @param {string} name        Block name.
 * @param {Array}  innerBlocks Inner blocks.
 *
 * @return {Object} Block.
 */
const container = ( clientId, name, innerBlocks ) => ( {
	clientId,
	name,
	attributes: {},
	innerBlocks,
} );

/**
 * Find a block in a tree by its client ID, as the block editor store does.
 *
 * @param {Array}  blocks   Blocks.
 * @param {string} clientId Block client ID.
 *
 * @return {Object|null} Block.
 */
const findById = ( blocks, clientId ) => {
	for ( const block of blocks ) {
		if ( clientId === block.clientId ) {
			return block;
		}
		const found = findById( block.innerBlocks || [], clientId );
		if ( found ) {
			return found;
		}
	}
	return null;
};

/**
 * Build the registry select function that a binding source gets.
 *
 * @param {Object} options
 * @param {Array}  options.blocks         Blocks in the editor canvas.
 * @param {*}      options.parentQueryIds Query blocks around the heading, outermost first.
 * @param {Object} options.records        Saved posts by "postType:ID".
 * @param {*}      options.postTypes      Registered post types, undefined while they load.
 *
 * @return {Function} Select function.
 */
const createRegistrySelect = ( {
	blocks = [],
	parentQueryIds = [],
	records = {},
	postTypes,
} ) => {
	const blockEditor = {
		getBlockParentsByBlockName: jest.fn( () => parentQueryIds ),
		getBlock: ( clientId ) => findById( blocks, clientId ),
		getBlocks: () => blocks,
	};

	return ( storeName ) => {
		if ( 'core/block-editor' === storeName ) {
			return blockEditor;
		}
		if ( 'core/editor' === storeName ) {
			return {
				getCurrentPostId: () => 7,
				getCurrentPostType: () => 'page',
			};
		}
		return {
			getPostType: () => null,
			getPostTypes: () => postTypes,
			getEntityRecord: ( kind, type, id ) =>
				records[ `${ type }:${ id }` ] ?? null,
		};
	};
};

/**
 * Get the heading for a heading block.
 *
 * @param {Function} select   Registry select function.
 * @param {string}   clientId Heading block client ID.
 * @param {Object}   context  Block context of the heading.
 *
 * @return {string} Heading content.
 */
const getHeading = ( select, clientId = 'heading', context = {} ) =>
	sources[ 'gatherpress/calendar-heading' ].getValues( {
		select,
		clientId,
		context,
	} ).content;

// Saved dates of an event from 3 November to 2 December 2026.
const WINTER_EVENT = {
	meta: {
		gatherpress_datetime_start: '2026-11-03 10:00:00',
		gatherpress_datetime_end: '2026-12-02 12:00:00',
	},
	title: { rendered: 'Winter Event' },
};

describe( 'calendar binding registration', () => {
	it( 'registers the calendar heading source', () => {
		expect( sources[ 'gatherpress/calendar-heading' ] ).toMatchObject( {
			label: 'Calendar Heading',
			usesContext: [ 'query', 'postId', 'postType' ],
		} );
	} );

	it( 'registers the calendar day source with the day context', () => {
		expect( sources[ 'gatherpress/calendar-day' ] ).toMatchObject( {
			label: 'Calendar Day Number',
			usesContext: [
				'gatherpress/dayNumber',
				'gatherpress/dayDate',
				'gatherpress/isEmpty',
			],
		} );
	} );

	it( 'adds the day number controls to the block editor', () => {
		expect( addFilter ).toHaveBeenCalledWith(
			'editor.BlockEdit',
			'gatherpress/day-number-binding-controls',
			expect.any( Function ),
		);
	} );
} );

describe( 'gatherpress/calendar-heading getValues', () => {
	beforeEach( () => {
		// The site starts the week on Monday.
		wpSelect.mockImplementation( ( storeName ) =>
			'core/editor' === storeName
				? {
					getEditorSettings: () => ( {
						gatherpress: { startOfWeek: 1 },
					} ),
				}
				: undefined,
		);
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'gives the range of the calendar in the editor canvas', () => {
		const select = createRegistrySelect( {
			blocks: [
				container( 'group', 'core/group', [
					calendar( 'cal', {
						viewType: 'month',
						unitCount: 2,
						selectedDate: '2026-03-15',
					} ),
				] ),
			],
		} );

		expect( getHeading( select ) ).toBe( 'month 2026-03-01 to 2026-04-30' );
		expect(
			select( 'core/block-editor' ).getBlockParentsByBlockName,
		).toHaveBeenCalledWith( 'heading', 'core/query' );
	} );

	it( 'uses the calendar in the closest Query block around the heading', () => {
		const select = createRegistrySelect( {
			blocks: [
				calendar( 'canvas-cal', { selectedDate: '2026-01-10' } ),
				container( 'outer-query', 'core/query', [
					calendar( 'outer-cal', {
						viewType: 'week',
						selectedDate: '2026-07-01',
					} ),
					container( 'inner-query', 'core/query', [
						calendar( 'inner-cal', {
							viewType: 'day',
							unitCount: 3,
							selectedDate: '2026-05-04',
						} ),
					] ),
				] ),
			],
			parentQueryIds: [ 'outer-query', 'inner-query' ],
		} );

		expect( getHeading( select ) ).toBe( 'day 2026-05-04 to 2026-05-06' );
	} );

	it( 'uses the calendar in the editor canvas when the Query block has none', () => {
		const select = createRegistrySelect( {
			blocks: [
				container( 'query', 'core/query', [
					container( 'heading', 'core/heading', [] ),
				] ),
				calendar( 'cal', { selectedDate: '2026-02-10' } ),
			],
			parentQueryIds: [ 'query' ],
		} );

		expect( getHeading( select ) ).toBe( 'month 2026-02-01 to 2026-02-28' );
	} );

	it( 'uses the calendar in the editor canvas when the Query block is not found', () => {
		const blocks = [ calendar( 'cal', { selectedDate: '2026-02-10' } ) ];

		expect(
			getHeading(
				createRegistrySelect( { blocks, parentQueryIds: [ 'gone' ] } ),
			),
		).toBe( 'month 2026-02-01 to 2026-02-28' );
		expect(
			getHeading( createRegistrySelect( { blocks, parentQueryIds: null } ) ),
		).toBe( 'month 2026-02-01 to 2026-02-28' );
	} );

	it( 'gives the current month when there is no calendar block', () => {
		jest.useFakeTimers();
		jest.setSystemTime( new Date( 2026, 9, 22, 12, 0, 0 ) );

		const select = createRegistrySelect( {
			blocks: [ container( 'group', 'core/group', [] ) ],
		} );

		expect( getHeading( select ) ).toBe( 'month 2026-10-01 to 2026-10-31' );
	} );

	it( 'starts the week on the start of week of the site', () => {
		const blocks = [
			calendar( 'cal', { viewType: 'week', selectedDate: '2026-10-22' } ),
		];

		expect( getHeading( createRegistrySelect( { blocks } ) ) ).toBe(
			'week 2026-10-19 to 2026-10-25',
		);
	} );

	it( 'leaves out the weekend when the calendar hides weekends', () => {
		const blocks = [
			calendar( 'cal', {
				viewType: 'week',
				selectedDate: '2026-10-22',
				showWeekends: false,
			} ),
		];

		expect( getHeading( createRegistrySelect( { blocks } ) ) ).toBe(
			'week 2026-10-19 to 2026-10-23',
		);
	} );

	it( 'moves the range by the date modifier for the default source', () => {
		const blocks = [
			calendar( 'cal', { selectedDate: '2026-03-15', dateModifier: 2 } ),
		];

		expect( getHeading( createRegistrySelect( { blocks } ) ) ).toBe(
			'month 2026-05-01 to 2026-05-31',
		);
	} );

	it( 'spans the months of the selected event and ignores the date modifier', () => {
		const select = createRegistrySelect( {
			blocks: [
				calendar( 'cal', {
					selectedDate: '2026-01-15',
					dateModifier: 2,
					dateRangeSource: 'selected',
					postId: 12,
					sourcePostType: 'gatherpress_event',
				} ),
			],
			records: { 'gatherpress_event:12': WINTER_EVENT },
		} );

		expect( getHeading( select ) ).toBe( 'month 2026-11-01 to 2026-12-31' );
	} );

	it( 'uses the event of the Query Loop item around the heading for the current source, as the calendar does', () => {
		// The editor edits page 7. The heading and the calendar sit in the post template of an outer loop.
		const select = createRegistrySelect( {
			blocks: [
				calendar( 'cal', {
					selectedDate: '2026-01-15',
					dateRangeSource: 'context',
				} ),
			],
			records: { 'gatherpress_event:30': WINTER_EVENT },
		} );

		expect(
			getHeading( select, 'heading', {
				postId: 30,
				postType: 'gatherpress_event',
			} ),
		).toBe( 'month 2026-11-01 to 2026-12-31' );
	} );

	it( 'looks up a selected event without a post type as the first event post type, as the calendar does', () => {
		const select = createRegistrySelect( {
			blocks: [
				calendar( 'cal', {
					selectedDate: '2026-01-15',
					dateRangeSource: 'selected',
					postId: 12,
				} ),
			],
			postTypes: [
				{ slug: 'page', supports: {} },
				{
					slug: 'meetup',
					supports: { 'gatherpress-event-date': true },
				},
				{
					slug: 'gatherpress_event',
					supports: { 'gatherpress-event-date': true },
				},
			],
			records: { 'meetup:12': WINTER_EVENT },
		} );

		expect( getHeading( select ) ).toBe( 'month 2026-11-01 to 2026-12-31' );
	} );

	it( 'ignores the date modifier when the selected event is not loaded', () => {
		const select = createRegistrySelect( {
			blocks: [
				calendar( 'cal', {
					selectedDate: '2026-01-15',
					dateModifier: 2,
					dateRangeSource: 'selected',
					postId: 99,
				} ),
			],
		} );

		expect( getHeading( select ) ).toBe( 'month 2026-01-01 to 2026-01-31' );
	} );
} );

describe( 'gatherpress/calendar-day getValues', () => {
	const { getValues } = sources[ 'gatherpress/calendar-day' ];

	it( 'gives an empty string for an empty cell', () => {
		expect(
			getValues( {
				context: {
					'gatherpress/isEmpty': true,
					'gatherpress/dayNumber': 22,
					'gatherpress/dayDate': '2026-10-22',
				},
				args: { format: 'jS' },
			} ),
		).toEqual( { content: '' } );
	} );

	it( 'formats the day date in UTC with the format from the binding', () => {
		const context = {
			'gatherpress/dayNumber': 22,
			'gatherpress/dayDate': '2026-10-22',
		};

		expect( getValues( { context, args: { format: 'jS' } } ) ).toEqual( {
			content: '22nd',
		} );
		expect( getValues( { context, args: { format: 'D j' } } ) ).toEqual( {
			content: 'Thu 22',
		} );
		expect(
			getValues( {
				context: { 'gatherpress/dayDate': '2026-10-05' },
				args: { format: 'd' },
			} ),
		).toEqual( { content: '05' } );
	} );

	it( 'gives the day number when the binding has no format', () => {
		const context = {
			'gatherpress/dayNumber': 22,
			'gatherpress/dayDate': '2026-10-22',
		};

		expect( getValues( { context } ) ).toEqual( { content: '22' } );
		expect( getValues( { context, args: { format: '' } } ) ).toEqual( {
			content: '22',
		} );
	} );

	it( 'gives the day number when there is no day date', () => {
		expect(
			getValues( {
				context: { 'gatherpress/dayNumber': 0 },
				args: { format: 'jS' },
			} ),
		).toEqual( { content: '0' } );
	} );

	it( 'gives an empty string when there is no day number', () => {
		expect(
			getValues( { context: { 'gatherpress/dayNumber': null } } ),
		).toEqual( { content: '' } );
		expect( getValues( {} ) ).toEqual( { content: '' } );
	} );
} );

describe( 'day number binding controls', () => {
	/**
	 * Stand-in for the edit component of the block.
	 *
	 * @param {Object} props
	 * @param {string} props.name Block name.
	 *
	 * @return {JSX.Element} Element.
	 */
	const BlockEdit = ( { name } ) => <p>Edit { name }</p>;
	const FilteredBlockEdit = withDayNumberBindingControls( BlockEdit );

	it( 'shows only the block when it is not bound to the calendar day source', () => {
		const { rerender } = render(
			<FilteredBlockEdit
				name="core/paragraph"
				attributes={ {
					metadata: {
						bindings: { content: { source: 'core/post-meta' } },
					},
				} }
				setAttributes={ jest.fn() }
			/>,
		);

		expect( screen.getByText( 'Edit core/paragraph' ) ).toBeInTheDocument();
		expect( screen.queryByRole( 'combobox' ) ).not.toBeInTheDocument();

		rerender( <FilteredBlockEdit name="core/heading" /> );

		expect( screen.getByText( 'Edit core/heading' ) ).toBeInTheDocument();
		expect( screen.queryByRole( 'combobox' ) ).not.toBeInTheDocument();
	} );

	it( 'shows the date format control with the format of the binding', () => {
		render(
			<FilteredBlockEdit
				name="core/paragraph"
				attributes={ {
					metadata: {
						bindings: {
							content: {
								source: 'gatherpress/calendar-day',
								args: { format: 'jS' },
							},
						},
					},
				} }
				setAttributes={ jest.fn() }
			/>,
		);

		expect( screen.getByText( 'Edit core/paragraph' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'region', { name: 'Day Number Settings' } ),
		).toBeInTheDocument();

		const control = screen.getByRole( 'combobox', { name: 'Date Format' } );

		expect( control ).toHaveValue( 'jS' );
		expect(
			Array.from( control.options ).map( ( option ) => option.value ),
		).toEqual( [ 'j', 'd', 'jS', 'j.', 'D j' ] );
	} );

	it( 'saves a new format and keeps the other metadata and binding args', () => {
		const setAttributes = jest.fn();

		render(
			<FilteredBlockEdit
				name="core/paragraph"
				attributes={ {
					metadata: {
						name: 'Day',
						bindings: {
							url: { source: 'core/post-meta' },
							content: {
								source: 'gatherpress/calendar-day',
								args: { format: 'jS', key: 'value' },
							},
						},
					},
				} }
				setAttributes={ setAttributes }
			/>,
		);

		fireEvent.change(
			screen.getByRole( 'combobox', { name: 'Date Format' } ),
			{ target: { value: 'd' } },
		);

		expect( setAttributes ).toHaveBeenCalledWith( {
			metadata: {
				name: 'Day',
				bindings: {
					url: { source: 'core/post-meta' },
					content: {
						source: 'gatherpress/calendar-day',
						args: { format: 'd', key: 'value' },
					},
				},
			},
		} );
	} );

	it( 'adds the format to a binding that has no args', () => {
		const setAttributes = jest.fn();

		render(
			<FilteredBlockEdit
				name="core/paragraph"
				attributes={ {
					metadata: {
						bindings: {
							content: { source: 'gatherpress/calendar-day' },
						},
					},
				} }
				setAttributes={ setAttributes }
			/>,
		);

		fireEvent.change(
			screen.getByRole( 'combobox', { name: 'Date Format' } ),
			{ target: { value: 'D j' } },
		);

		expect( setAttributes ).toHaveBeenCalledWith( {
			metadata: {
				bindings: {
					content: {
						source: 'gatherpress/calendar-day',
						args: { format: 'D j' },
					},
				},
			},
		} );
	} );
} );

describe( 'without the Block Bindings API', () => {
	it( 'registers no binding source, but still adds the day number controls', () => {
		// The mocked module is shared with the isolated registry below.
		const blocks = require( '@wordpress/blocks' );
		const register = blocks.registerBlockBindingsSource;

		register.mockClear();
		addFilter.mockClear();
		blocks.registerBlockBindingsSource = undefined;

		try {
			expect( () =>
				jest.isolateModules( () => {
					require( '@src/blocks/calendar/bindings' );
				} ),
			).not.toThrow();
		} finally {
			blocks.registerBlockBindingsSource = register;
		}

		expect( register ).not.toHaveBeenCalled();
		expect( addFilter ).toHaveBeenCalledTimes( 1 );
		expect( addFilter ).toHaveBeenCalledWith(
			'editor.BlockEdit',
			'gatherpress/day-number-binding-controls',
			expect.any( Function ),
		);
	} );
} );
