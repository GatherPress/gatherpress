/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';
import '@testing-library/jest-dom';
import { render, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ViewerTimeTooltip from '@src/blocks/event-date/viewer-time-tooltip';

describe( 'ViewerTimeTooltip', () => {
	it( 'leaves a plain datetime unwrapped', () => {
		const { container } = render(
			<ViewerTimeTooltip isLink={ false } label="">
				June 15
			</ViewerTimeTooltip>,
		);

		expect( container.innerHTML ).toBe( 'June 15' );
	} );

	it( 'wraps a link without a tooltip in a pseudo link that does not navigate', () => {
		const { getByRole } = render(
			<ViewerTimeTooltip isLink label="">
				June 15
			</ViewerTimeTooltip>,
		);
		const link = getByRole( 'link' );
		const click = new MouseEvent( 'click', { bubbles: true, cancelable: true } );
		const preventDefault = jest.spyOn( click, 'preventDefault' );

		fireEvent( link, click );

		expect( preventDefault ).toHaveBeenCalled();
		expect( link ).not.toHaveClass( 'gatherpress-tooltip' );
		expect( link ).not.toHaveAttribute( 'tabindex' );
		expect( link ).not.toHaveAttribute( 'data-gatherpress-tooltip' );
	} );

	it( 'carries the tooltip and its screen reader text on a link', () => {
		const { getByRole } = render(
			<ViewerTimeTooltip isLink label="1:00 PM your time">
				June 15
			</ViewerTimeTooltip>,
		);
		const link = getByRole( 'link' );

		expect( link ).toHaveClass( 'gatherpress-tooltip' );
		expect( link ).toHaveAttribute( 'data-gatherpress-tooltip', '1:00 PM your time' );
		expect( link ).toHaveTextContent( 'June 15 (1:00 PM your time)' );
	} );

	it( 'makes a plain datetime with a tooltip focusable', () => {
		const { container } = render(
			<ViewerTimeTooltip isLink={ false } label="1:00 PM your time">
				June 15
			</ViewerTimeTooltip>,
		);
		const span = container.firstChild;

		expect( span.tagName ).toBe( 'SPAN' );
		expect( span ).toHaveAttribute( 'tabindex', '0' );
		expect( span ).toHaveClass( 'gatherpress-tooltip' );
		expect( span.querySelector( '.gatherpress-tooltip-notice' ) ).toHaveTextContent(
			'(1:00 PM your time)',
		);
	} );
} );
