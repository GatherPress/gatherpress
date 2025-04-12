/**
 * External dependencies.
 */
import { render, act } from '@testing-library/react';
import { expect, test } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Internal dependencies.
 */
import MapEmbed from '../../../../../src/components/MapEmbed';

/**
 * Coverage for MapEmbed.
 */
test('MapEmbed returns empty when no location is provided', () => {
	const { container } = render(<MapEmbed />);

	expect(container).toHaveTextContent('');
});

test('OSM MapEmbed returns a div when location is set', async () => {
	global.GatherPress = {
		settings: {
			mapPlatform: 'osm',
		},
	};

	let container;

	await act(async () => {
		const result = render(
			<MapEmbed location="50 South Fullerton Avenue, Montclair, NJ 07042" />
		);
		container = result.container;
	});

	expect(container).toContainHTML('<div></div>');
});

test('Google MapEmbed returns address in source when location is set', () => {
	global.GatherPress = {
		settings: {
			mapPlatform: 'google',
		},
	};
	const { container } = render(
		<MapEmbed
			location="50 South Fullerton Avenue, Montclair, NJ 07042"
			latitude="40.8117036"
			longitude="-74.2187738"
		/>
	);

	expect(container.children[0].getAttribute('src')).toContain(
		'?q=40.8117036%2C-74.2187738'
	);
	expect(container.children[0].getAttribute('src')).toContain('&z=10');
	expect(container.children[0].getAttribute('src')).toContain('&t=m');
	expect(container.children[0].getAttribute('src')).toContain(
		'&output=embed'
	);
	expect(container.children[0]).toHaveStyle(
		'border: 0px; height: 300px; width: 100%;'
	);
});

test('MapEmbed returns address in source when location, zoom, map type, height, and class are set', () => {
	const { container } = render(
		<MapEmbed
			location="50 South Fullerton Avenue, Montclair, NJ 07042"
			latitude="40.8117036"
			longitude="-74.2187738"
			zoom={20}
			type="k"
			className="unit-test"
			height={100}
		/>
	);
	expect(container.children[0].getAttribute('src')).toContain(
		'?q=40.8117036%2C-74.2187738'
	);
	expect(container.children[0].getAttribute('src')).toContain('&z=20');
	expect(container.children[0].getAttribute('src')).toContain('&t=k');
	expect(container.children[0]).toHaveStyle(
		'border: 0px; height: 100px; width: 100%;'
	);
	expect(container.children[0]).toHaveClass('unit-test');
});
