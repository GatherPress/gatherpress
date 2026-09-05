/**
 * Announce GatherPress links that open in a new tab.
 *
 * PHP adds the notice when a block renders. This covers links that appear
 * or change afterwards, so a block that swaps a span for a link at runtime
 * gets the same treatment without knowing this exists.
 */

const { blockPrefix, noticeClass, noticeText } =
	window.gatherPressNewTabNotice || {};

/**
 * Add the notice to a link that opens in a new tab and lacks one.
 *
 * @param {HTMLAnchorElement} link The link to announce.
 *
 * @return {void}
 */
const announce = ( link ) => {
	if ( link.querySelector( `.${ noticeClass }` ) ) {
		return;
	}

	const notice = document.createElement( 'span' );

	notice.className = `screen-reader-text ${ noticeClass }`;
	notice.textContent = noticeText;
	link.appendChild( notice );
};

/**
 * Announce every new-tab link inside a root element.
 *
 * @param {Element} root The element to search.
 *
 * @return {void}
 */
const announceWithin = ( root ) => {
	root.querySelectorAll?.( 'a[target="_blank"]' ).forEach( announce );

	if ( 'A' === root.tagName && '_blank' === root.getAttribute( 'target' ) ) {
		announce( root );
	}
};

/**
 * Watch GatherPress blocks for links added or changed after render.
 *
 * @return {void}
 */
const start = () => {
	if ( ! noticeClass || ! noticeText ) {
		return;
	}

	const blocks = document.querySelectorAll( `[class*="${ blockPrefix }"]` );

	if ( ! blocks.length ) {
		return;
	}

	const observer = new MutationObserver( ( records ) => {
		records.forEach( ( record ) => {
			record.addedNodes.forEach( announceWithin );

			if ( 'attributes' === record.type ) {
				announceWithin( record.target );
			}
		} );
	} );

	blocks.forEach( ( block ) => {
		announceWithin( block );
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
