/**
 * Highlighting helper for documentation screenshots.
 *
 * Playwright's own `locator.highlight()` is a debugging tool and is not
 * guaranteed to survive into captured screenshots, so docs images draw
 * attention to an element by injecting a small stylesheet and tagging the
 * target element with a class instead.
 */

/**
 * GatherPress brand indigo, as used on gatherpress.org.
 */
const HIGHLIGHT_COLOR = '#4F46E5';

const HIGHLIGHT_CLASS = 'gatherpress-docs-highlight';

const HIGHLIGHT_STYLE = `
	.${ HIGHLIGHT_CLASS } {
		outline: 3px solid ${ HIGHLIGHT_COLOR } !important;
		outline-offset: 3px;
		border-radius: 2px;
	}
`;

/**
 * Draws a GatherPress-branded outline around an element before a screenshot.
 *
 * @param page    The Playwright page the element lives on.
 * @param locator Locator for the element to highlight.
 */
export async function highlight( page, locator ): Promise< void > {
	await page.addStyleTag( { content: HIGHLIGHT_STYLE } );
	await locator.evaluate( ( element, className ) => {
		element.classList.add( className );
	}, HIGHLIGHT_CLASS );
}
