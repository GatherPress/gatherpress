/**
 * WordPress dependencies.
 */
import { dispatch } from '@wordpress/data';
import { getBlockType } from '@wordpress/blocks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { useState, useEffect } from '@wordpress/element';

/**
 * Higher-Order Component to add BlockGuard functionality to supported GatherPress blocks.
 *
 * This HOC injects a toggle control into the Inspector Controls of blocks
 * that support `blockGuard`, enabling users to toggle block protection.
 * When enabled (default), an invisible overlay prevents interactions with inner blocks.
 *
 * @param {Function} BlockEdit - The original BlockEdit component.
 * @param            props
 * @return {Function} Enhanced BlockEdit component with BlockGuard functionality.
 *
 * @example
 * // Usage:
 * // In a block's `block.json`, add the following to enable this feature:
 * {
 *   "supports": {
 *     "gatherpress": {
 *       "blockGuard": true
 *     }
 *   }
 * }
 */
const withBlockGuard = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { name, clientId } = props;

		// Check if the block supports blockGuard.
		if (
			!name.startsWith('gatherpress/') ||
			!getBlockType(name)?.supports?.gatherpress?.blockGuard
		) {
			return <BlockEdit {...props} />;
		}

		// Use state to track if BlockGuard is enabled (default to enabled).
		const [isBlockGuardEnabled, setIsBlockGuardEnabled] = useState(true);

		useEffect(() => {
			if (!clientId) {
				return;
			}

			const applyBlockGuard = () => {
				const blockElement = document.getElementById(
					`block-${clientId}`
				);

				if (!blockElement) {
					return;
				}

				const innerBlocksContainer = blockElement.querySelector(
					'.block-editor-inner-blocks'
				);

				if (!innerBlocksContainer) {
					return;
				}

				// Handle tabindex elements
				const elementsWithTabindex =
					innerBlocksContainer.querySelectorAll('[tabindex]');

				elementsWithTabindex.forEach((el) => {
					if (isBlockGuardEnabled) {
						if (!el.dataset.originalTabIndex) {
							el.dataset.originalTabIndex =
								el.getAttribute('tabindex');
						}
						el.setAttribute('tabindex', '-1');
					} else if (el.dataset.originalTabIndex) {
						el.setAttribute(
							'tabindex',
							el.dataset.originalTabIndex
						);
						delete el.dataset.originalTabIndex;
					}
				});

				// Handle contenteditable elements.
				const editableElements = innerBlocksContainer.querySelectorAll(
					'[contenteditable], [data-original-contenteditable]'
				);

				editableElements.forEach((el) => {
					if (isBlockGuardEnabled) {
						if ('true' === el.getAttribute('contenteditable')) {
							el.dataset.originalContentEditable = 'true';
							el.setAttribute('contenteditable', 'false');
						}
					} else if ('true' === el.dataset.originalContentEditable) {
						el.setAttribute('contenteditable', 'true');
						delete el.dataset.originalContentEditable;
					}
				});

				// Handle block appender visibility.
				const blockAppender = innerBlocksContainer.querySelector(
					'.block-list-appender'
				);

				if (blockAppender) {
					blockAppender.style.display = isBlockGuardEnabled
						? 'none'
						: '';
				}

				// Handle overlay.
				let overlay = innerBlocksContainer.querySelector(
					'.gatherpress-block-guard-overlay'
				);

				if (!overlay) {
					overlay = document.createElement('div');
					overlay.className = 'gatherpress-block-guard-overlay';

					overlay.style.position = 'absolute';
					overlay.style.top = '0';
					overlay.style.left = '0';
					overlay.style.width = '100%';
					overlay.style.height = '100%';
					overlay.style.zIndex = '999999';
					overlay.style.background = 'transparent';

					overlay.onclick = (e) => {
						e.stopPropagation();
						dispatch('core/block-editor').selectBlock(clientId);
					};

					// Ensure position relative on container.
					innerBlocksContainer.style.position = 'relative';
					innerBlocksContainer.appendChild(overlay);
				}

				// Toggle overlay visibility.
				overlay.style.display = isBlockGuardEnabled ? 'block' : 'none';
			};

			// Apply initially.
			applyBlockGuard();

			// Set up observer for DOM changes.
			const observer = new MutationObserver(applyBlockGuard);
			observer.observe(document.body, { childList: true, subtree: true });

			return () => {
				observer.disconnect();

				// Clean up overlay on unmount.
				const blockElement = document.getElementById(
					`block-${clientId}`
				);
				const innerBlocks = blockElement?.querySelector(
					'.block-editor-inner-blocks'
				);
				const overlay = innerBlocks?.querySelector(
					'.gatherpress-block-guard-overlay'
				);
				if (overlay && overlay.parentNode) {
					overlay.parentNode.removeChild(overlay);
				}
			};
		}, [clientId, isBlockGuardEnabled]);

		// Handle List View behavior.
		useEffect(() => {
			if (!clientId) {
				return;
			}

			const handleListView = () => {
				// Find the list view item.
				const listViewItem = document.querySelector(
					`.block-editor-list-view-leaf[data-block="${clientId}"]`
				);
				if (!listViewItem) {
					return;
				}

				// Find the expander.
				const expander = listViewItem.querySelector(
					'.block-editor-list-view__expander'
				);

				if (!expander) {
					return;
				}

				// Find the SVG inside the expander.
				const expanderSvg = expander.querySelector('svg');

				if (!expanderSvg) {
					return;
				}

				if (isBlockGuardEnabled) {
					// Store expanded state.
					const isExpanded =
						'true' === listViewItem.getAttribute('data-expanded');

					// If expanded, collapse it.
					if (isExpanded) {
						expander.click();
					}

					// Make expander non-interactive but preserve layout.
					expander.style.pointerEvents = 'none';
					expander.style.opacity = '0.3';

					// Disable the parent link element.
					const parentLink = expander.closest(
						'.block-editor-list-view-block-select-button'
					);

					if (parentLink) {
						parentLink.setAttribute('aria-expanded', 'false');
						parentLink.style.pointerEvents = 'none';

						// Re-enable just the link itself, but not the expander.
						setTimeout(() => {
							parentLink.style.pointerEvents = 'auto';
							expander.style.pointerEvents = 'none';
						}, 0);
					}
				} else {
					// Restore interactivity.
					expander.style.pointerEvents = '';
					expander.style.opacity = '';

					// Re-enable the parent link.
					const parentLink = expander.closest(
						'.block-editor-list-view-block-select-button'
					);
					if (parentLink) {
						parentLink.style.pointerEvents = '';
					}
				}
			};

			setTimeout(handleListView, 100);

			const observer = new MutationObserver(() =>
				setTimeout(handleListView, 50)
			);

			observer.observe(document.body, { childList: true, subtree: true });

			return () => observer.disconnect();
		}, [clientId, isBlockGuardEnabled]);

		return (
			<>
				<BlockEdit {...props} />
				<InspectorControls>
					<PanelBody>
						<ToggleControl
							label={__('Block Guard', 'gatherpress')}
							checked={isBlockGuardEnabled}
							onChange={setIsBlockGuardEnabled}
							help={
								isBlockGuardEnabled
									? __(
											'Block protection is enabled. Click to focus on parent block.',
											'gatherpress'
										)
									: __(
											'Block protection is disabled. Inner blocks can be freely edited.',
											'gatherpress'
										)
							}
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}, 'withBlockGuard');

/**
 * Register the HOC as a filter for the BlockEdit component.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/
 */
addFilter('editor.BlockEdit', 'gatherpress/with-block-guard', withBlockGuard);
