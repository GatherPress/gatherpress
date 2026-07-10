/**
 * Format type name constant.
 *
 * @type {string}
 */
export const FORMAT_NAME = 'gatherpress/tooltip';

/**
 * Default color values for tooltips in the editor UI.
 *
 * These are the fallback colors shown in the color pickers. When a tooltip
 * is created with these default colors, the color attributes are NOT stored,
 * allowing the theme's CSS custom properties to control the appearance.
 *
 * Themes can customize tooltip colors by setting these CSS custom properties:
 *   --gatherpress--tooltip--text-color
 *   --gatherpress--tooltip--background-color
 *
 * If not set, tooltips will use WordPress global styles variables
 * (--wp--preset--color--*) or fall back to these values.
 *
 * @type {Object}
 */
export const DEFAULT_COLORS = {
	textColor: '#ffffff',
	bgColor: '#333333',
};
