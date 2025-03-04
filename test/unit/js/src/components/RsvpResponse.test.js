/**
 * External dependencies.
 */
import { render } from '@testing-library/react';
import { describe, expect, it } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Internal dependencies.
 */
import RsvpResponse from '../../../../../src/components/RsvpResponse';

/**
 * Coverage for RsvpResponse.
 */
describe('RsvpResponse', () => {
	it('renders component correctly', () => {
		global.GatherPress = {
			eventDetails: {
				responses: {
					all: {
						count: 1,
						records: [
							{
								guests: 0,
								id: 1,
								name: 'unittest',
								photo: 'https://unit.test/photo',
								profile: 'https://unit.test/profile',
								role: 'Member',
								status: 'attending',
								timestamp: '2023-05-11 00:00:00',
							},
						],
					},
					attending: {
						count: 1,
						records: [
							{
								guests: 0,
								id: 1,
								name: 'John Doe',
								photo: 'https://unit.test/photo',
								profile: 'https://unit.test/profile',
								role: 'Member',
								status: 'attending',
								timestamp: '2023-05-11 00:00:00',
							},
						],
					},
					not_attending: {
						count: 0,
						records: [],
					},
					waiting_list: {
						count: 0,
						records: [],
					},
				},
			},
		};

		const { container } = render(<RsvpResponse />);

		expect(container.children[0]).toHaveClass('gatherpress-rsvp-response');
		expect(container.children[0]).toHaveTextContent('John Doe');
	});
});
