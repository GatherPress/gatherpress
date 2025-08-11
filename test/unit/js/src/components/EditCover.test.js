/**
 * External dependencies.
 */
import { render } from '@testing-library/react';
import { expect, test } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Internal dependencies.
 */
import EditCover from '../../../../../src/components/EditCover';

/**
 * Coverage for EditCover.
 */
test( 'EditCover is display block by default', () => {
	const { container } = render( <EditCover /> );

	expect( container.children[ 0 ] ).toHaveStyle( 'position: relative' );
	expect( container.children[ 0 ].children[ 0 ] ).toHaveStyle( 'display: block' );
} );

test( 'EditCover is display block when isSelected is false', () => {
	const { container } = render( <EditCover isSelected={ false } /> );

	expect( container.children[ 0 ] ).toHaveStyle( 'position: relative' );
	expect( container.children[ 0 ].children[ 0 ] ).toHaveStyle( 'display: block' );
} );

test( 'EditCover is display none when isSelected is true', () => {
	const { container } = render( <EditCover isSelected={ true } /> );

	expect( container.children[ 0 ] ).toHaveStyle( 'position: relative' );
	expect( container.children[ 0 ].children[ 0 ] ).toHaveStyle( 'display: none' );
} );
