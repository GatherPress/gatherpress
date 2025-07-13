/**
 * External dependencies.
 */
import { describe, expect, it, beforeEach, afterEach } from '@jest/globals';

/**
 * Internal dependencies.
 */
import {
	getFromGlobal,
	setToGlobal,
	toCamelCase,
	safeHTML,
} from '../../../../../src/helpers/globals';

/**
 * Coverage for getFromGlobal and setToGlobal.
 */
describe('Global GatherPress functions', () => {
	// Setup and teardown for the global GatherPress object
	beforeEach(() => {
		// Create mock GatherPress global
		global.GatherPress = {
			config: {
				apiUrl: 'https://api.example.com',
				nonce: '1234abcd',
			},
			data: {
				events: [],
				user: {
					id: 1,
					name: 'Test User',
				},
			},
		};
	});

	afterEach(() => {
		// Clean up mock
		delete global.GatherPress;
	});

	describe('getFromGlobal', () => {
		it('retrieves a top-level property from the global GatherPress object', () => {
			expect(getFromGlobal('config')).toEqual({
				apiUrl: 'https://api.example.com',
				nonce: '1234abcd',
			});
		});

		it('retrieves a nested property using dot notation', () => {
			expect(getFromGlobal('config.apiUrl')).toBe(
				'https://api.example.com'
			);
			expect(getFromGlobal('data.user.name')).toBe('Test User');
		});

		it('returns undefined for non-existent properties', () => {
			expect(getFromGlobal('nonExistent')).toBeUndefined();
			expect(getFromGlobal('config.missing')).toBeUndefined();
			expect(getFromGlobal('data.user.email')).toBeUndefined();
		});

		it('returns undefined for deeply nested non-existent paths', () => {
			expect(
				getFromGlobal('a.very.deep.path.that.does.not.exist')
			).toBeUndefined();
		});

		it('returns undefined when GatherPress global is not defined', () => {
			delete global.GatherPress;
			expect(getFromGlobal('config')).toBeUndefined();
		});

		it('returns undefined when GatherPress is not an object', () => {
			global.GatherPress = 'not an object';
			expect(getFromGlobal('config')).toBeUndefined();
		});
	});

	describe('setToGlobal', () => {
		it('sets a value to an existing property', () => {
			setToGlobal('config.apiUrl', 'https://new-api.example.com');
			expect(global.GatherPress.config.apiUrl).toBe(
				'https://new-api.example.com'
			);
		});

		it('creates a new property at an existing level', () => {
			setToGlobal('config.version', '1.0.0');
			expect(global.GatherPress.config.version).toBe('1.0.0');
		});

		it('creates a new nested property structure', () => {
			setToGlobal('settings.theme.darkMode', true);
			expect(global.GatherPress.settings.theme.darkMode).toBe(true);
		});

		it('can set various data types as values', () => {
			// String.
			setToGlobal('testString', 'hello');
			expect(global.GatherPress.testString).toBe('hello');

			// Number.
			setToGlobal('testNumber', 42);
			expect(global.GatherPress.testNumber).toBe(42);

			// Boolean.
			setToGlobal('testBoolean', false);
			expect(global.GatherPress.testBoolean).toBe(false);

			// Object.
			setToGlobal('testObject', { key: 'value' });
			expect(global.GatherPress.testObject).toEqual({ key: 'value' });

			// Array.
			setToGlobal('testArray', [1, 2, 3]);
			expect(global.GatherPress.testArray).toEqual([1, 2, 3]);

			// Null.
			setToGlobal('testNull', null);
			expect(global.GatherPress.testNull).toBeNull();
		});

		it('does nothing when GatherPress global is not defined', () => {
			delete global.GatherPress;

			// This should not throw an error.
			setToGlobal('config.apiUrl', 'test');

			// GatherPress should still be undefined.
			expect(global.GatherPress).toBeUndefined();
		});

		it('does nothing when GatherPress is not an object', () => {
			global.GatherPress = 'not an object';

			// This should not throw an error.
			setToGlobal('config.apiUrl', 'test');

			// GatherPress should remain unchanged.
			expect(global.GatherPress).toBe('not an object');
		});

		it('preserves existing properties when adding new ones', () => {
			setToGlobal('data.newProperty', 'new value');

			expect(global.GatherPress.data.events).toEqual([]);
			expect(global.GatherPress.data.user).toEqual({
				id: 1,
				name: 'Test User',
			});
			expect(global.GatherPress.data.newProperty).toBe('new value');
		});
	});
});

/**
 * Coverage for safeHTML.
 */
describe('safeHTML', () => {
	it('removes script tags from HTML', () => {
		const html = '<div>Safe content<script>alert("xss");</script></div>';
		const sanitized = safeHTML(html);

		expect(sanitized).not.toContain('<script>');
		expect(sanitized).toContain('<div>Safe content</div>');
	});

	it('removes onclick attributes from HTML elements', () => {
		const html = '<button onclick="alert(\'xss\')">Click me</button>';
		const sanitized = safeHTML(html);

		expect(sanitized).not.toContain('onclick');
		expect(sanitized).toContain('<button>Click me</button>');
	});

	it('removes multiple on* event handlers from HTML elements', () => {
		const html =
			'<div onmouseover="alert(1)" onload="alert(2)" onclick="alert(3)">Test</div>';
		const sanitized = safeHTML(html);

		expect(sanitized).not.toContain('onmouseover');
		expect(sanitized).not.toContain('onload');
		expect(sanitized).not.toContain('onclick');
		expect(sanitized).toContain('<div>Test</div>');
	});

	it('handles nested elements with unsafe attributes', () => {
		const html =
			'<div><p onclick="bad()">Text</p><span onmouseover="evil()">More</span></div>';
		const sanitized = safeHTML(html);

		expect(sanitized).not.toContain('onclick');
		expect(sanitized).not.toContain('onmouseover');
		expect(sanitized).toContain('<div><p>Text</p><span>More</span></div>');
	});

	it('handles nested script tags', () => {
		const html =
			'<div>Start<script>bad code</script>Middle<script>more bad</script>End</div>';
		const sanitized = safeHTML(html);

		expect(sanitized).not.toContain('<script>');
		expect(sanitized).toContain('<div>StartMiddleEnd</div>');
	});

	it('preserves safe HTML content and attributes', () => {
		const html =
			'<a href="https://example.com" target="_blank" class="link">Safe Link</a>';
		const sanitized = safeHTML(html);

		expect(sanitized).toContain('href="https://example.com"');
		expect(sanitized).toContain('target="_blank"');
		expect(sanitized).toContain('class="link"');
		expect(sanitized).toContain('>Safe Link</a>');
	});

	it('handles empty input', () => {
		expect(safeHTML('')).toBe('');
	});

	it('handles plain text without HTML', () => {
		const text = 'Just some plain text without any HTML';

		expect(safeHTML(text)).toBe(text);
	});

	it('handles malformed HTML gracefully', () => {
		const malformed = '<div>Unclosed div<script>alert("bad");</script>';
		const sanitized = safeHTML(malformed);

		expect(sanitized).not.toContain('<script>');
		expect(sanitized).toContain('<div>Unclosed div');
	});
});

/**
 * Coverage for toCamelCase.
 */
describe('toCamelCase', () => {
	it('converts a simple snake_case string to camelCase', () => {
		expect(toCamelCase('hello_world')).toBe('helloWorld');
	});

	it('converts a multi-word snake_case string to camelCase', () => {
		expect(toCamelCase('not_attending_the_event')).toBe(
			'notAttendingTheEvent'
		);
	});

	it('handles strings that are already camelCase', () => {
		expect(toCamelCase('helloWorld')).toBe('helloWorld');
	});

	it('handles single word strings without underscores', () => {
		expect(toCamelCase('hello')).toBe('hello');
	});

	it('handles empty strings', () => {
		expect(toCamelCase('')).toBe('');
	});

	it('handles strings with consecutive underscores', () => {
		expect(toCamelCase('hello__world')).toBe('helloWorld');
	});

	it('handles strings with three or more consecutive underscores', () => {
		expect(toCamelCase('hello___world')).toBe('helloWorld');
	});

	it('preserves uppercase letters after underscores', () => {
		expect(toCamelCase('hello_World')).toBe('helloWorld');
	});

	it('handles uppercase letters in the middle of words', () => {
		expect(toCamelCase('heLLo_world')).toBe('heLLoWorld');
	});
});
