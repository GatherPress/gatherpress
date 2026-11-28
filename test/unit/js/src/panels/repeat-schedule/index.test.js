/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
import { usePostTypeSupports } from '@src/helpers/event';
import { RepeatSchedulePanel } from '@src/panels/repeat-schedule';

jest.mock( '@wordpress/plugins', () => ( { registerPlugin: jest.fn() } ) );
jest.mock( '@wordpress/editor', () => ( {
	PluginDocumentSettingPanel: ( { title, children } ) => (
		<section aria-label={ title }>{ children }</section>
	),
} ) );
jest.mock( '@src/helpers/event', () => ( { usePostTypeSupports: jest.fn() } ) );
jest.mock( '@src/components/RepeatSchedule', () => () => <p>Repeat controls</p> );

describe( 'Repeat schedule panel', () => {
	it( 'shows the repeat controls in their own panel on an event', () => {
		usePostTypeSupports.mockReturnValue( true );
		render( <RepeatSchedulePanel /> );

		expect( screen.getByRole( 'region', { name: 'Repeat schedule' } ) ).toHaveTextContent( 'Repeat controls' );
	} );

	it( 'shows nothing on a post type without event dates', () => {
		usePostTypeSupports.mockReturnValue( false );
		const { container } = render( <RepeatSchedulePanel /> );

		expect( container ).toBeEmptyDOMElement();
	} );
} );
