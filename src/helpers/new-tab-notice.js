/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Announce GatherPress links that open in a new tab.
 *
 * PHP adds the notice when a block renders. This covers links that appear or
 * change afterwards, so a block that swaps a span for a link at runtime gets
 * the same treatment without knowing this exists.
 *
 * The notice follows the target rather than being added once. A block that
 * hides a link by swapping in a span copies the content across verbatim,
 * notice included, and would otherwise leave static text announcing that it
 * opens a new tab. The same holds for a link retargeted away from `_blank`.
 */
const BLOCK_SELECTOR = '[class*="wp-block-gatherpress-"]';
const NOTICE_CLASS = 'gatherpress-new-tab-notice';

/**
 * Add the notice to a link that opens in a new tab and lacks one.
 *
 * @param {Element} link The link to announce.
 *
 * @return {void}
 */
const announce = ( link ) => {
	if ( link.querySelector( `.${ NOTICE_CLASS }` ) ) {
		return;
	}

	const notice = document.createElement( 'span' );

	notice.className = `screen-reader-text ${ NOTICE_CLASS }`;
	// The space is outside the string, as core does it, so the label and the
	// notice cannot run together in the accessible name.
	notice.textContent = ` ${ __( '(opens in a new tab)', 'gatherpress' ) }`;
	link.appendChild( notice );
};

/**
 * Whether a link opens in a new tab.
 *
 * Target keywords beginning with an underscore are case-insensitive, so
 * `_BLANK` opens a new tab just as `_blank` does.
 *
 * @param {Element} link The link to check.
 *
 * @return {boolean} True when the link opens in a new tab.
 */
const opensNewTab = ( link ) =>
	'_blank' === ( link.getAttribute( 'target' ) || '' ).toLowerCase();

/**
 * Remove a notice that no longer describes where it sits.
 *
 * A notice belongs inside a link that opens a new tab and nowhere else. Left
 * behind in plain text, or in a link that now opens in the same tab, it tells
 * a screen reader something that is not true.
 *
 * @param {Element} notice The notice to check.
 *
 * @return {void}
 */
const withdraw = ( notice ) => {
	const link = notice.closest( 'a' );

	if ( ! link || ! opensNewTab( link ) ) {
		notice.remove();
	}
};

/**
 * Bring the notices inside an element, and the element itself, into line with
 * what its links actually do: added where a new-tab link lacks one, removed
 * where one has outlived its link.
 *
 * @param {Element} root The element to search.
 *
 * @return {void}
 */
const reconcileWithin = ( root ) => {
	root.querySelectorAll?.( 'a[target]' ).forEach( ( link ) => {
		if ( opensNewTab( link ) ) {
			announce( link );
		}
	} );

	if ( 'A' === root.tagName && opensNewTab( root ) ) {
		announce( root );
	}

	root.querySelectorAll?.( `.${ NOTICE_CLASS }` ).forEach( withdraw );

	if ( root.classList?.contains( NOTICE_CLASS ) ) {
		withdraw( root );
	}
};

/**
 * Watch GatherPress blocks for links added or retargeted after render.
 *
 * @return {void}
 */
const start = () => {
	const blocks = document.querySelectorAll( BLOCK_SELECTOR );

	if ( ! blocks.length ) {
		return;
	}

	const observer = new MutationObserver( ( records ) => {
		records.forEach( ( record ) => {
			record.addedNodes.forEach( reconcileWithin );

			if ( 'attributes' === record.type ) {
				reconcileWithin( record.target );
			}
		} );
	} );

	blocks.forEach( ( block ) => {
		reconcileWithin( block );
		observer.observe( block, {
			attributeFilter: [ 'target' ],
			childList: true,
			subtree: true,
		} );
	} );
};

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', start );
} else {
	start();
}
