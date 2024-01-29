/**
 * External dependencies.
 */
import { render } from '@testing-library/react';
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

	expect(container).toBeEmptyDOMElement();
});

test('MapEmbed returns address in source when location is set', () => {
	const { container } = render(
		<MapEmbed location="50 South Fullerton Avenue, Montclair, NJ 07042" />
	);

	expect(container.children[0].getAttribute('src')).toContain(
		'?q=50+South+Fullerton+Avenue%2C+Montclair%2C+NJ+07042'
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
			zoom={20}
			type="k"
			className="unit-test"
			height={100}
		/>
	);
	expect(container.children[0].getAttribute('src')).toContain(
		'q=50+South+Fullerton+Avenue%2C+Montclair%2C+NJ+07042'
	);
	expect(container.children[0].getAttribute('src')).toContain('&z=20');
	expect(container.children[0].getAttribute('src')).toContain('&t=k');
	expect(container.children[0]).toHaveStyle(
		'border: 0px; height: 100px; width: 100%;'
	);
	expect(container.children[0]).toHaveClass('unit-test');
});
