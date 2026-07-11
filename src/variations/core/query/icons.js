/**
 * This file replicates how WP core provides icons for its "Start blank" variations picker.
 * Inspired by: https://raw.githubusercontent.com/WordPress/gutenberg/b08653ae83a20f69f4657f1da11b2e3d27683f5a/packages/block-library/src/query/icons.js
 */

/**
 * WordPress dependencies
 */
import { Path, SVG } from '@wordpress/components';

/**
 * Event Date + Title
 *
 * Layout per card:
 *   - Short bar (date): y=9, h=1, partial width
 *   - Tall bar (title): y=12, h=3, full width
 * Repeated for second card at y=27.
 */
export const eventDateTitle = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
		<Path d="M19 9H7v1h12V9zm22 3H7v3h34v-3zM19 27H7v1h12v-1zm22 3H7v3h34v-3z" />
	</SVG>
);

/**
 * Event Date + Title + Excerpt
 *
 * Layout per card:
 *   - Two thin bars (excerpt): y=17 and y=19, near-full width
 */
export const eventDateTitleExcerpt = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
		<Path d="M19 9H7v1h12V9zm22 3H7v3h34v-3zm-4 5H7v1h30v-1zm4 2H7v1h34v-1zM19 27H7v1h12v-1zm22 3H7v3h34v-3zm-4 5H7v1h30v-1zm4 2H7v1h34v-1z" />
	</SVG>
);

/**
 * Image + Event Date + Title
 */
export const imageEventDateTitle = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
		<Path d="M7 9h18v8H7V9zM7 9l18 8M29 10h10v1H29v-1zm0 3h12v4H29v-4zM7 27h18v8H7V27zM7 27l18 8M29 28h10v1H29v-1zm0 3h12v4H29v-4z" />
	</SVG>
);

/**
 * Event Date + Title + Venue + Map
 */
export const eventDateTitleVenueMap = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
		<Path d="M7 8h14v1H7V8zm0 3h24v3H7v-3zm0 7h18v1H7v-1zm0 3h34v14H7V21z" />
		<Path d="M24 25a2 2 0 100 4 2 2 0 000-4zm0 7c-2-2-4-4-4-6a4 4 0 118 0c0 2-2 4-4 6z" />
	</SVG>
);
