/**
 * External dependencies.
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';

jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
} ) );

jest.mock( '@wordpress/core-data', () => ( {
	store: 'core',
} ) );

/**
 * WordPress dependencies.
 */
import { select as wpSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import {
	DATE_SOURCE_CONTEXT,
	DATE_SOURCE_DEFAULT,
	DATE_SOURCE_SELECTED,
	getDatetimeSeparator,
	getDefaultEventPostType,
	getLiveEventDates,
	isContextDateSource,
	isPostDateSource,
	isSelectedDateSource,
	normalizeDateSource,
	resolveSourceEventDates,
	selectEventPostTypes,
	toDateString,
} from '@src/blocks/calendar/utils/source-utils';

/**
 * Build a select function for the stores that resolveSourceEventDates reads.
 *
 * @param {Object}   options
 * @param {*}        options.editorPostId    ID of the post the editor edits.
 * @param {string}   options.editorPostType  Post type of the post the editor edits.
 * @param {Object}   options.records         Saved posts by ID.
 * @param {string}   options.title           Edited title of the post the editor edits.
 * @param {Object}   options.editedMeta      Edited meta of the post the editor edits.
 * @param {*}        options.dateTimeStart   Start that the datetime store gives.
 * @param {*}        options.dateTimeEnd     End that the datetime store gives.
 * @param {Function} options.getEntityRecord Replaces the lookup in `records`.
 *
 * @return {Function} Select function.
 */
const createSelect =
	( {
		editorPostId,
		editorPostType,
		records = {},
		title = 'Edited Event',
		editedMeta = {},
		dateTimeStart = '2026-10-22 18:00:00',
		dateTimeEnd = '2026-10-22 20:00:00',
		getEntityRecord = ( kind, type, id ) => records[ id ] ?? null,
	} ) =>
		( storeName ) => {
			if ( 'core/editor' === storeName ) {
				return {
					getCurrentPostId: () => editorPostId,
					getCurrentPostType: () => editorPostType,
					getEditedPostAttribute: ( key ) =>
						'title' === key ? title : editedMeta,
				};
			}
			if ( 'gatherpress/datetime' === storeName ) {
				// The datetime store always has dates, also in a template.
				return {
					getDateTimeStart: () => dateTimeStart,
					getDateTimeEnd: () => dateTimeEnd,
				};
			}
			return {
				getPostType: ( type ) =>
					'gatherpress_event' === type
						? { supports: { 'gatherpress-event-date': true } }
						: null,
				getEntityRecord,
			};
		};

describe( 'getDatetimeSeparator', () => {
	beforeEach( () => {
		wpSelect.mockReset();
	} );

	/**
	 * Make the editor store publish a datetime separator.
	 *
	 * @param {*} datetimeSeparator Separator in the GatherPress config.
	 */
	const publishSeparator = ( datetimeSeparator ) => {
		wpSelect.mockImplementation( ( storeName ) =>
			'core/editor' === storeName
				? {
					getEditorSettings: () => ( {
						gatherpress: { config: { datetimeSeparator } },
					} ),
				}
				: undefined,
		);
	};

	it( 'gives the separator that GatherPress core publishes, without outer spaces', () => {
		publishSeparator( '  to  ' );

		expect( getDatetimeSeparator() ).toBe( 'to' );
		expect( wpSelect ).toHaveBeenCalledWith( 'core/editor' );
	} );

	it( 'gives the en dash when the published separator is only spaces', () => {
		publishSeparator( '   ' );

		expect( getDatetimeSeparator() ).toBe( '–' );
	} );

	it( 'gives the en dash when the published separator is not a string', () => {
		publishSeparator( 5 );

		expect( getDatetimeSeparator() ).toBe( '–' );
	} );

	it( 'gives the en dash when there is no editor store or no editor settings', () => {
		wpSelect.mockReturnValue( undefined );
		expect( getDatetimeSeparator() ).toBe( '–' );

		wpSelect.mockReturnValue( {} );
		expect( getDatetimeSeparator() ).toBe( '–' );
	} );
} );

describe( 'normalizeDateSource', () => {
	it( 'maps the context aliases to context', () => {
		expect( normalizeDateSource( 'context' ) ).toBe( DATE_SOURCE_CONTEXT );
		expect( normalizeDateSource( 'current_post' ) ).toBe(
			DATE_SOURCE_CONTEXT,
		);
		expect( normalizeDateSource( 'current' ) ).toBe( DATE_SOURCE_CONTEXT );
	} );

	it( 'maps the selected aliases to selected', () => {
		expect( normalizeDateSource( 'selected' ) ).toBe( DATE_SOURCE_SELECTED );
		expect( normalizeDateSource( 'specific_post' ) ).toBe(
			DATE_SOURCE_SELECTED,
		);
		expect( normalizeDateSource( 'specific' ) ).toBe( DATE_SOURCE_SELECTED );
	} );

	it( 'maps every other value to default', () => {
		expect( normalizeDateSource( 'default' ) ).toBe( DATE_SOURCE_DEFAULT );
		expect( normalizeDateSource( 'Context' ) ).toBe( DATE_SOURCE_DEFAULT );
		expect( normalizeDateSource( '' ) ).toBe( DATE_SOURCE_DEFAULT );
		expect( normalizeDateSource( undefined ) ).toBe( DATE_SOURCE_DEFAULT );
	} );
} );

describe( 'date source checks', () => {
	it( 'treats context and selected sources as anchored to an event post', () => {
		expect( isPostDateSource( 'current_post' ) ).toBe( true );
		expect( isPostDateSource( 'specific' ) ).toBe( true );
		expect( isPostDateSource( 'default' ) ).toBe( false );
		expect( isPostDateSource( undefined ) ).toBe( false );
	} );

	it( 'detects the context source by any of its names', () => {
		expect( isContextDateSource( 'current' ) ).toBe( true );
		expect( isContextDateSource( 'selected' ) ).toBe( false );
		expect( isContextDateSource( 'default' ) ).toBe( false );
	} );

	it( 'detects the selected source by any of its names', () => {
		expect( isSelectedDateSource( 'specific_post' ) ).toBe( true );
		expect( isSelectedDateSource( 'context' ) ).toBe( false );
		expect( isSelectedDateSource( 'default' ) ).toBe( false );
	} );
} );

describe( 'selectEventPostTypes', () => {
	it( 'gives the post types that support event dates, in their order', () => {
		const meetup = {
			slug: 'meetup',
			supports: { 'gatherpress-event-date': true },
		};
		const event = {
			slug: 'gatherpress_event',
			supports: { 'gatherpress-event-date': true },
		};
		const getPostTypes = jest.fn( () => [
			{ slug: 'page', supports: { editor: true } },
			meetup,
			{ slug: 'attachment' },
			event,
		] );
		const select = jest.fn( () => ( { getPostTypes } ) );

		expect( selectEventPostTypes( select ) ).toEqual( [ meetup, event ] );
		expect( select ).toHaveBeenCalledWith( 'core' );
		expect( getPostTypes ).toHaveBeenCalledWith( {
			per_page: -1,
			context: 'edit',
		} );
	} );

	it( 'gives the same empty list each time while the post types load', () => {
		const select = () => ( { getPostTypes: () => null } );
		const first = selectEventPostTypes( select );

		expect( first ).toEqual( [] );
		expect( selectEventPostTypes( select ) ).toBe( first );
	} );
} );

describe( 'getDefaultEventPostType', () => {
	it( 'gives the slug of the first event post type', () => {
		expect(
			getDefaultEventPostType( [ { slug: 'tour_date' }, { slug: 'other' } ] ),
		).toBe( 'tour_date' );
	} );

	it( 'gives gatherpress_event when the first post type has no slug', () => {
		expect( getDefaultEventPostType( [ {} ] ) ).toBe( 'gatherpress_event' );
	} );

	it( 'gives gatherpress_event when there are no event post types', () => {
		expect( getDefaultEventPostType() ).toBe( 'gatherpress_event' );
		expect( getDefaultEventPostType( [] ) ).toBe( 'gatherpress_event' );
		expect( getDefaultEventPostType( null ) ).toBe( 'gatherpress_event' );
		expect( getDefaultEventPostType( 'tour_date' ) ).toBe(
			'gatherpress_event',
		);
	} );
} );

describe( 'toDateString', () => {
	it( 'gives an empty string for an empty value', () => {
		expect( toDateString( '' ) ).toBe( '' );
		expect( toDateString( null ) ).toBe( '' );
		expect( toDateString( undefined ) ).toBe( '' );
		expect( toDateString( 0 ) ).toBe( '' );
	} );

	it( 'keeps the date part of a datetime string', () => {
		expect( toDateString( '2026-10-22 18:00:00' ) ).toBe( '2026-10-22' );
		expect( toDateString( '2026-10-22' ) ).toBe( '2026-10-22' );
	} );

	it( 'gives an empty string for a string that is too short to hold a date', () => {
		expect( toDateString( '2026-10' ) ).toBe( '' );
	} );

	it( 'reads a timestamp in seconds or in milliseconds as a local date', () => {
		const date = new Date( 2026, 9, 22, 12, 0, 0 );

		expect( toDateString( Math.floor( date.getTime() / 1000 ) ) ).toBe(
			'2026-10-22',
		);
		expect( toDateString( date.getTime() ) ).toBe( '2026-10-22' );
	} );

	it( 'pads the month and the day of a timestamp with a zero', () => {
		const date = new Date( 2026, 0, 5, 12, 0, 0 );

		expect( toDateString( date.getTime() ) ).toBe( '2026-01-05' );
	} );

	it( 'gives an empty string for a number that is not a valid date', () => {
		expect( toDateString( Infinity ) ).toBe( '' );
		expect( toDateString( 1e20 ) ).toBe( '' );
	} );

	it( 'reads a Date object as a local date', () => {
		expect( toDateString( new Date( 2026, 2, 7, 23, 30, 0 ) ) ).toBe(
			'2026-03-07',
		);
	} );

	it( 'gives an empty string for an invalid Date object', () => {
		expect( toDateString( new Date( 'not a date' ) ) ).toBe( '' );
	} );

	it( 'reads the date or the dateTime property of an object', () => {
		expect( toDateString( { date: '2026-10-22T18:00:00' } ) ).toBe(
			'2026-10-22',
		);
		expect( toDateString( { dateTime: '2026-11-03 10:00:00' } ) ).toBe(
			'2026-11-03',
		);
		expect(
			toDateString( { date: '2026', dateTime: '2026-11-03 10:00:00' } ),
		).toBe( '2026-11-03' );
	} );

	it( 'gives an empty string for an object without a usable date', () => {
		expect( toDateString( {} ) ).toBe( '' );
		expect( toDateString( { date: 20261022, dateTime: '2026' } ) ).toBe( '' );
	} );

	it( 'gives an empty string for a value of another type', () => {
		expect( toDateString( true ) ).toBe( '' );
	} );
} );

describe( 'getLiveEventDates', () => {
	it( 'gives null when select is not a function', () => {
		expect( getLiveEventDates() ).toBeNull();
		expect( getLiveEventDates( {} ) ).toBeNull();
	} );

	it( 'reads the dates from the datetime store', () => {
		const select = createSelect( {
			dateTimeStart: '2026-10-22 18:00:00',
			dateTimeEnd: '2026-10-24 20:00:00',
			editedMeta: {
				gatherpress_datetime_start: '2026-01-01 00:00:00',
				gatherpress_datetime_end: '2026-01-02 00:00:00',
			},
		} );

		expect( getLiveEventDates( select ) ).toEqual( {
			startDate: '2026-10-22',
			endDate: '2026-10-24',
		} );
	} );

	it( 'reads the edited meta when the datetime store has no dates', () => {
		const select = createSelect( {
			dateTimeStart: '',
			dateTimeEnd: null,
			editedMeta: {
				gatherpress_datetime_start: '2026-11-03 10:00:00',
				gatherpress_datetime_end: '2026-11-05 12:00:00',
			},
		} );

		expect( getLiveEventDates( select ) ).toEqual( {
			startDate: '2026-11-03',
			endDate: '2026-11-05',
		} );
	} );

	it( 'uses the start date as the end date when there is no end date', () => {
		const select = createSelect( {
			dateTimeEnd: '',
			editedMeta: {},
		} );

		expect( getLiveEventDates( select ) ).toEqual( {
			startDate: '2026-10-22',
			endDate: '2026-10-22',
		} );
	} );

	it( 'moves an end date that is before the start date to the start date', () => {
		const select = createSelect( {
			dateTimeStart: '2026-10-22 18:00:00',
			dateTimeEnd: '2026-10-20 20:00:00',
		} );

		expect( getLiveEventDates( select ) ).toEqual( {
			startDate: '2026-10-22',
			endDate: '2026-10-22',
		} );
	} );

	it( 'gives null when there is no start date', () => {
		const select = createSelect( {
			dateTimeStart: '',
			editedMeta: { gatherpress_datetime_end: '2026-11-05 12:00:00' },
		} );

		expect( getLiveEventDates( select ) ).toBeNull();
	} );

	it( 'gives null when the stores are not registered', () => {
		expect( getLiveEventDates( () => undefined ) ).toBeNull();
	} );
} );

describe( 'resolveSourceEventDates', () => {
	const noPost = {
		startDate: '',
		endDate: '',
		postTitle: '',
		hasPost: false,
		effectivePostId: 0,
	};

	it( 'gives no post for the default source, without reading a store', () => {
		const select = jest.fn();

		expect( resolveSourceEventDates( select ) ).toEqual( noPost );
		expect(
			resolveSourceEventDates( select, {
				dateRangeSource: 'default',
				postId: 12,
			} ),
		).toEqual( noPost );
		expect( select ).not.toHaveBeenCalled();
	} );

	it( 'gives no post for a site editor template in Current mode', () => {
		const select = createSelect( {
			editorPostId: 'twentytwentyfive//single-gatherpress_event',
			editorPostType: 'wp_template',
		} );

		expect(
			resolveSourceEventDates( select, { dateRangeSource: 'context' } ),
		).toMatchObject( { hasPost: false, effectivePostId: 0 } );
	} );

	it( 'gives no post in Selected mode when no post is selected', () => {
		const select = createSelect( {
			editorPostId: 7,
			editorPostType: 'gatherpress_event',
		} );

		expect(
			resolveSourceEventDates( select, { dateRangeSource: 'selected' } ),
		).toEqual( noPost );
		expect(
			resolveSourceEventDates( select, {
				dateRangeSource: 'specific_post',
				postId: -4,
			} ),
		).toEqual( noPost );
	} );

	it( 'reads the live dates only for the event that the editor edits', () => {
		const select = createSelect( {
			editorPostId: 7,
			editorPostType: 'gatherpress_event',
			records: {
				12: {
					meta: {
						gatherpress_datetime_start: '2026-11-03 10:00:00',
						gatherpress_datetime_end: '2026-11-04 12:00:00',
					},
					title: { rendered: 'Loop Event' },
				},
			},
		} );

		expect(
			resolveSourceEventDates( select, { dateRangeSource: 'context' } ),
		).toMatchObject( {
			startDate: '2026-10-22',
			postTitle: 'Edited Event',
			effectivePostId: 7,
		} );
		expect(
			resolveSourceEventDates( select, {
				dateRangeSource: 'context',
				contextPostId: 12,
				contextPostType: 'gatherpress_event',
			} ),
		).toMatchObject( {
			startDate: '2026-11-03',
			endDate: '2026-11-04',
			postTitle: 'Loop Event',
			effectivePostId: 12,
		} );
	} );

	it( 'reads the live dates for the selected post when the editor edits it', () => {
		const getEntityRecord = jest.fn();
		const select = createSelect( {
			editorPostId: 7,
			editorPostType: 'gatherpress_event',
			dateTimeEnd: '2026-10-23 20:00:00',
			getEntityRecord,
		} );

		expect(
			resolveSourceEventDates( select, {
				dateRangeSource: 'selected',
				postId: '7',
			} ),
		).toEqual( {
			startDate: '2026-10-22',
			endDate: '2026-10-23',
			postTitle: 'Edited Event',
			hasPost: true,
			effectivePostId: 7,
		} );
		expect( getEntityRecord ).not.toHaveBeenCalled();
	} );

	it( 'names the edited event by its ID when its title is empty', () => {
		const select = createSelect( {
			editorPostId: 7,
			editorPostType: 'gatherpress_event',
			title: '',
		} );

		expect(
			resolveSourceEventDates( select, { dateRangeSource: 'current' } ),
		).toMatchObject( { postTitle: '#7', hasPost: true } );
	} );

	it( 'reads the saved dates when the edited event has no live dates', () => {
		const select = createSelect( {
			editorPostId: 7,
			editorPostType: 'gatherpress_event',
			dateTimeStart: '',
			dateTimeEnd: '',
			records: {
				7: {
					meta: {
						gatherpress_datetime_start: '2026-09-01 10:00:00',
						gatherpress_datetime_end: '2026-09-02 10:00:00',
					},
					title: { rendered: 'Saved Event' },
				},
			},
		} );

		expect(
			resolveSourceEventDates( select, { dateRangeSource: 'context' } ),
		).toEqual( {
			startDate: '2026-09-01',
			endDate: '2026-09-02',
			postTitle: 'Saved Event',
			hasPost: true,
			effectivePostId: 7,
		} );
	} );

	it( 'decodes the HTML entities of the rendered title', () => {
		const select = createSelect( {
			editorPostId: 3,
			editorPostType: 'page',
			records: {
				12: {
					meta: {
						gatherpress_datetime_start: '2026-09-01 10:00:00',
					},
					title: { rendered: 'Sam&#8217;s Meetup &amp; Talk' },
				},
			},
		} );

		expect(
			resolveSourceEventDates( select, {
				dateRangeSource: 'selected',
				postId: 12,
			} ).postTitle,
		).toBe( 'Sam’s Meetup & Talk' );
	} );

	it( 'reads the saved dates when the editor post type has no event dates', () => {
		const select = createSelect( {
			editorPostId: 7,
			editorPostType: 'page',
			records: {
				7: {
					meta: {
						gatherpress_datetime_start: '2026-09-01 10:00:00',
					},
					title: { raw: 'Raw Title' },
				},
			},
		} );

		expect(
			resolveSourceEventDates( select, { dateRangeSource: 'context' } ),
		).toEqual( {
			startDate: '2026-09-01',
			endDate: '2026-09-01',
			postTitle: 'Raw Title',
			hasPost: true,
			effectivePostId: 7,
		} );
	} );

	it( 'reads the selected post with its own post type', () => {
		const getEntityRecord = jest.fn( () => ( {
			meta: { gatherpress_datetime_start: '2026-12-01 10:00:00' },
			title: { rendered: 'Tour Date' },
		} ) );
		const select = createSelect( {
			editorPostId: 7,
			editorPostType: 'gatherpress_event',
			getEntityRecord,
		} );

		expect(
			resolveSourceEventDates( select, {
				dateRangeSource: 'selected',
				postId: 12,
				sourcePostType: 'tour_date',
			} ),
		).toMatchObject( { startDate: '2026-12-01', hasPost: true } );
		expect( getEntityRecord ).toHaveBeenCalledWith(
			'postType',
			'tour_date',
			12,
		);
	} );

	it( 'reads the selected post with the default post type when it has none', () => {
		const getEntityRecord = jest.fn( () => null );
		const select = createSelect( {
			editorPostId: 7,
			editorPostType: 'gatherpress_event',
			getEntityRecord,
		} );

		resolveSourceEventDates( select, {
			dateRangeSource: 'selected',
			postId: 12,
			defaultPostType: 'tour_date',
		} );

		expect( getEntityRecord ).toHaveBeenCalledWith(
			'postType',
			'tour_date',
			12,
		);
	} );

	it( 'reads a context post with the editor post type, then the default post type', () => {
		const getEntityRecord = jest.fn( () => null );

		resolveSourceEventDates(
			createSelect( {
				editorPostId: 7,
				editorPostType: 'page',
				getEntityRecord,
			} ),
			{ dateRangeSource: 'context', contextPostId: 12 },
		);
		resolveSourceEventDates(
			createSelect( {
				editorPostId: 7,
				editorPostType: undefined,
				getEntityRecord,
			} ),
			{ dateRangeSource: 'context', contextPostId: 12 },
		);

		expect( getEntityRecord ).toHaveBeenNthCalledWith(
			1,
			'postType',
			'page',
			12,
		);
		expect( getEntityRecord ).toHaveBeenNthCalledWith(
			2,
			'postType',
			'gatherpress_event',
			12,
		);
	} );

	it( 'keeps the post ID but gives no post when the post is not loaded', () => {
		const select = createSelect( {
			editorPostId: 7,
			editorPostType: 'gatherpress_event',
		} );

		expect(
			resolveSourceEventDates( select, {
				dateRangeSource: 'selected',
				postId: 12,
			} ),
		).toEqual( { ...noPost, effectivePostId: 12 } );
	} );

	it( 'moves a saved end date that is before the start date to the start date', () => {
		const select = createSelect( {
			editorPostId: 7,
			editorPostType: 'gatherpress_event',
			records: {
				12: {
					meta: {
						gatherpress_datetime_start: '2026-11-03 10:00:00',
						gatherpress_datetime_end: '2026-11-01 12:00:00',
					},
					title: { rendered: 'Odd Event' },
				},
			},
		} );

		expect(
			resolveSourceEventDates( select, {
				dateRangeSource: 'selected',
				postId: 12,
			} ),
		).toMatchObject( { startDate: '2026-11-03', endDate: '2026-11-03' } );
	} );

	it( 'names a saved post without a title by its ID, and gives no post without a start date', () => {
		const select = createSelect( {
			editorPostId: 7,
			editorPostType: 'gatherpress_event',
			records: { 12: {} },
		} );

		expect(
			resolveSourceEventDates( select, {
				dateRangeSource: 'selected',
				postId: 12,
			} ),
		).toEqual( {
			startDate: '',
			endDate: '',
			postTitle: '#12',
			hasPost: false,
			effectivePostId: 12,
		} );
	} );
} );
