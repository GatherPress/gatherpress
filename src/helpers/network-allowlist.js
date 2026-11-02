/**
 * Check or uncheck the inherited settings under one of the Select all links.
 *
 * A link inside a group covers that group, and the links above the groups
 * cover every group.
 *
 * @since TBD
 *
 * @param {HTMLElement} root    The allowlist container.
 * @param {HTMLElement} trigger The Select all or Deselect all button.
 *
 * @return {void}
 */
export function toggleAllowlist( root, trigger ) {
	const scope =
		trigger.closest( '.gatherpress-allowlist__group' ) ?? root;
	const checked = 'all' === trigger.dataset.gatherpress_select;

	scope
		.querySelectorAll( '.gatherpress-allowlist__options input[type="checkbox"]' )
		.forEach( ( checkbox ) => {
			checkbox.checked = checked;
		} );
}

/**
 * Wire up the Select all links on the Network tab of the network settings.
 *
 * Does nothing on pages without the allowlist.
 *
 * @since TBD
 *
 * @return {void}
 */
export function initNetworkAllowlist() {
	const root = document.querySelector(
		'[data-gatherpress_component_name="network-allowlist"]',
	);

	if ( ! root ) {
		return;
	}

	root.querySelectorAll( '[data-gatherpress_select]' ).forEach( ( trigger ) =>
		trigger.addEventListener( 'click', () => toggleAllowlist( root, trigger ) ),
	);
}
