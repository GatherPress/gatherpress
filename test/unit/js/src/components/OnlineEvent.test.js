/**
 * External dependencies.
 */
import { render } from '@testing-library/react';
import { expect, test } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Internal dependencies.
 */
import OnlineEvent from '../../../../../src/components/OnlineEvent';

/**
 * Coverage for OnlineEvent.
 */
test( 'OnlineEvent renders component without link', () => {
	const { container } = render( <OnlineEvent /> );

	expect( container ).toHaveTextContent( 'Online event' );
	expect( container.children[ 0 ].children[ 0 ].children[ 0 ] ).toHaveClass(
		'dashicon dashicons dashicons-video-alt2',
	);
	expect( container.children[ 0 ].children[ 1 ].children[ 0 ] ).toHaveClass(
		'gatherpress-tooltip',
	);
} );

test( 'OnlineEvent renders component with link', () => {
	const link = 'https://unit.test/chat/';
	const { container } = render( <OnlineEvent onlineEventLinkDefault={ link } /> );

	expect( container ).toHaveTextContent( 'Online event' );
	expect( container.children[ 0 ].children[ 0 ].children[ 0 ] ).toHaveClass(
		'dashicon dashicons dashicons-video-alt2',
	);
	expect(
		container.children[ 0 ].children[ 1 ].children[ 0 ].getAttribute( 'href' ),
	).toBe( link );
} );
