/**
 * External dependencies.
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';
import { renderHook } from '@testing-library/react';

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
} ) );

jest.mock( '@wordpress/core-data', () => ( {
	store: 'core',
} ) );

jest.mock( '@src/helpers/event', () => ( {
	usePostTypeSupports: jest.fn(),
} ) );

jest.mock( '@src/blocks/calendar/hooks/use-source-post-dates', () => ( {
	useSourcePostDates: jest.fn( () => ( {
		hasPost: false,
		startDate: '',
		endDate: '',
		effectivePostId: 0,
	} ) ),
} ) );

/**
 * WordPress dependencies.
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { usePostTypeSupports } from '@src/helpers/event';
import { useDateRangeSourceSync } from '@src/blocks/calendar/hooks/use-date-range-source-sync';

/**
 * Give the post type of the editor a load state and an event date support.
 *
 * @param {Object}  state
 * @param {boolean} state.resolved   Whether the post type has loaded.
 * @param {boolean} state.supported  Whether the post type supports event dates.
 * @param {string}  state.editorType Post type that the editor edits.
 *
 * @return {jest.Mock} The hasFinishedResolution mock.
 */
const givePostType = ( { resolved, supported, editorType = 'page' } ) => {
	const hasFinishedResolution = jest.fn( () => resolved );

	useSelect.mockImplementation( ( callback ) =>
		callback( ( storeName ) =>
			'core/editor' === storeName
				? { getCurrentPostType: () => editorType }
				: { hasFinishedResolution },
		),
	);
	usePostTypeSupports.mockReturnValue( supported );

	return hasFinishedResolution;
};

/**
 * Render the hook for a calendar with the "Current" date source.
 *
 * @param {Object} context Block context.
 *
 * @return {jest.Mock} The setAttributes mock.
 */
const renderCurrentSource = ( context = { postType: 'gatherpress_event' } ) => {
	const setAttributes = jest.fn();

	renderHook( () =>
		useDateRangeSourceSync( {
			attributes: { dateRangeSource: 'context' },
			setAttributes,
			context,
			startOfWeek: 1,
		} ),
	);

	return setAttributes;
};

describe( 'useDateRangeSourceSync', () => {
	beforeEach( () => {
		useSelect.mockReset();
		usePostTypeSupports.mockReset();
	} );

	it( 'keeps the "Current" source while the post type loads', () => {
		const hasFinishedResolution = givePostType( {
			resolved: false,
			supported: false,
		} );

		expect( renderCurrentSource() ).not.toHaveBeenCalled();
		expect( hasFinishedResolution ).toHaveBeenCalledWith( 'getPostType', [
			'gatherpress_event',
		] );
	} );

	it( 'resets the "Current" source when the loaded post type has no event dates', () => {
		givePostType( { resolved: true, supported: false } );

		expect( renderCurrentSource() ).toHaveBeenCalledWith( {
			dateRangeSource: 'default',
		} );
	} );

	it( 'keeps the "Current" source when the post type has event dates', () => {
		givePostType( { resolved: true, supported: true } );

		expect( renderCurrentSource() ).not.toHaveBeenCalled();
	} );

	it( 'checks the post type of the editor when the block context has none', () => {
		const hasFinishedResolution = givePostType( {
			resolved: false,
			supported: false,
			editorType: 'wp_template',
		} );

		renderCurrentSource( {} );

		expect( hasFinishedResolution ).toHaveBeenCalledWith( 'getPostType', [
			'wp_template',
		] );
	} );
} );
