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
 * Shared state store for block guard settings across all block instances.
 * This ensures that blocks of the same type (especially in query loops)
 * maintain consistent guard states.
 */
const blockGuardStates = new Map();
const blockGuardListeners = new Map();

/**
 * Custom hook to manage shared block guard state.
 *
 * @param {string} blockName - The name of the block type.
 * @return {Array} Array containing [isEnabled, setIsEnabled] similar to useState.
 */
function useSharedBlockGuardState( blockName ) {
	// Initialize state if it doesn't exist.
	if ( ! blockGuardStates.has( blockName ) ) {
		blockGuardStates.set( blockName, true ); // Default to enabled.
	}

	const [ localState, setLocalState ] = useState(
		blockGuardStates.get( blockName ),
	);

	useEffect( () => {
		// Initialize listeners array for this block type if it doesn't exist.
		if ( ! blockGuardListeners.has( blockName ) ) {
			blockGuardListeners.set( blockName, new Set() );
		}

		// Add this component's state setter to the listeners.
		const listeners = blockGuardListeners.get( blockName );
		listeners.add( setLocalState );

		// Sync with current shared state.
		setLocalState( blockGuardStates.get( blockName ) );

		// Cleanup: remove listener on unmount.
		return () => {
			listeners.delete( setLocalState );
		};
	}, [ blockName ] );

	const setSharedState = ( value ) => {
		// Update the shared state.
		blockGuardStates.set( blockName, value );

		// Notify all listeners (components using this block type).
		const listeners = blockGuardListeners.get( blockName );
		if ( listeners ) {
			listeners.forEach( ( listener ) => listener( value ) );
		}
	};

	return [ localState, setSharedState ];
}

/**
 * Get the appropriate document context for the block editor.
 *
 * In FSE (Full Site Editing) contexts, blocks are rendered within an iframe
 * with the name "editor-canvas". This function detects that iframe and returns
 * its document, otherwise falls back to the main document for regular editors.
 *
 * @return {Document} The document object containing the block editor content.
 */
function getEditorDocument() {
	const iframe = global.document.querySelector(
		'iframe[name="editor-canvas"]',
	);

	if ( iframe?.contentDocument ) {
		return iframe.contentDocument;
	}

	return global.document;
}

/**
 * Generate a unique state key for block guard state management.
 * This creates the right level of sharing vs independence:
 * - Individual blocks outside query loops: each gets unique state
 * - Individual blocks inside query loops: each gets unique state
 * - Repeated instances in query loops: same position shares state across posts
 *
 * @param {string} name     - The block type name.
 * @param {string} clientId - The current block's client ID.
 * @return {string} Unique state key for this block's context.
 */
function generateBlockGuardStateKey( name, clientId ) {
	const editorDoc = getEditorDocument();
	const currentBlockElement = editorDoc.getElementById( `block-${ clientId }` );

	if ( ! currentBlockElement ) {
		// Fallback: each block gets its own state.
		return `${ name }-${ clientId }`;
	}

	// Check if this block is in a query loop.
	const queryLoopContainer = currentBlockElement.closest(
		'[data-type="core/post-template"]',
	);

	if ( queryLoopContainer ) {
		// Find all blocks of the same type within this query loop template.
		const sameTypeBlocks = Array.from(
			queryLoopContainer.querySelectorAll( `[data-type="${ name }"]` ),
		);

		// Find the index of the current block within blocks of the same type.
		const blockIndex = sameTypeBlocks.findIndex(
			( block ) => block.id === `block-${ clientId }`,
		);

		if ( blockIndex !== -1 ) {
			// Create a unique key for this block position within query loops.
			// This ensures the 1st RSVP block across all posts shares state,
			// but is independent from the 2nd RSVP block.
			const queryLoopId =
				queryLoopContainer.closest( '[data-type="core/query"]' )?.id ||
				'unknown-query-loop';
			return `${ name }-queryloop-${ queryLoopId }-position-${ blockIndex }`;
		}
	}

	// For blocks outside query loops, each gets its own unique state.
	return `${ name }-${ clientId }`;
}

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
const withBlockGuard = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { name, clientId } = props;

		// Check if the block supports blockGuard.
		if (
			! name.startsWith( 'gatherpress/' ) ||
			! getBlockType( name )?.supports?.gatherpress?.blockGuard
		) {
			return <BlockEdit { ...props } />;
		}

		// Generate unique state key for appropriate sharing/independence.
		const stateKey = generateBlockGuardStateKey( name, clientId );

		// Use shared state to track if BlockGuard is enabled (default to enabled).
		const [ isBlockGuardEnabled, setIsBlockGuardEnabled ] =
			useSharedBlockGuardState( stateKey );

		useEffect( () => {
			if ( ! clientId ) {
				return;
			}

			const applyBlockGuard = () => {
				const editorDoc = getEditorDocument();
				const currentBlockElement = editorDoc.getElementById(
					`block-${ clientId }`,
				);

				if ( ! currentBlockElement ) {
					return;
				}

				// Find all blocks on the page that should share the same state as this block.
				const allBlocks = Array.from(
					editorDoc.querySelectorAll( `[data-type="${ name }"]` ),
				);
				const targetElements = [];

				// Filter blocks to only include those with the same state key.
				allBlocks.forEach( ( blockElement ) => {
					const blockId = blockElement.id?.replace( 'block-', '' );
					if ( blockId ) {
						const blockStateKey = generateBlockGuardStateKey(
							name,
							blockId,
						);
						if ( blockStateKey === stateKey ) {
							targetElements.push( blockElement );
						}
					}
				} );

				targetElements.forEach( ( blockElement ) => {
					const innerBlocksContainer = blockElement.querySelector(
						'.block-editor-inner-blocks',
					);

					if ( ! innerBlocksContainer ) {
						return;
					}

					// Handle focusable elements.
					const focusableElements =
						innerBlocksContainer.querySelectorAll( `
						a[href],
						button,
						input,
						textarea,
						select,
						details,
						iframe,
						[tabindex],
						[contentEditable="true"],
						audio[controls],
						video[controls],
						[role="button"],
						[role="link"],
						[role="checkbox"],
						[role="radio"],
						[role="combobox"],
						[role="menuitem"],
						[role="textbox"],
						[role="tab"]
					` );

					focusableElements.forEach( ( el ) => {
						if ( isBlockGuardEnabled ) {
							if ( ! el.dataset.originalTabIndex ) {
								el.dataset.originalTabIndex =
									el.getAttribute( 'tabindex' );
							}
							el.setAttribute( 'tabindex', '-1' );
						} else if ( el.dataset.originalTabIndex ) {
							el.setAttribute(
								'tabindex',
								el.dataset.originalTabIndex,
							);
							delete el.dataset.originalTabIndex;
						}
					} );

					// Handle block appender visibility.
					const blockAppender = innerBlocksContainer.querySelector(
						'.block-list-appender',
					);

					if ( blockAppender ) {
						blockAppender.style.display = isBlockGuardEnabled
							? 'none'
							: '';
					}

					// Handle overlay.
					let overlay = innerBlocksContainer.querySelector(
						'.gatherpress-block-guard-overlay',
					);

					if ( ! overlay ) {
						overlay = global.document.createElement( 'div' );
						overlay.className = 'gatherpress-block-guard-overlay';

						overlay.style.position = 'absolute';
						overlay.style.top = '0';
						overlay.style.left = '0';
						overlay.style.width = '100%';
						overlay.style.height = '100%';
						overlay.style.background = 'transparent';
						overlay.style.zIndex = '1';

						// Get the actual clientId of this specific block instance.
						const blockClientId =
							blockElement.id?.replace( 'block-', '' ) || clientId;
						overlay.onclick = ( e ) => {
							e.stopPropagation();
							dispatch( 'core/block-editor' ).selectBlock(
								blockClientId,
							);
						};

						// Ensure position relative on container.
						innerBlocksContainer.style.position = 'relative';
						innerBlocksContainer.appendChild( overlay );
					}

					// Toggle overlay visibility.
					overlay.style.display = isBlockGuardEnabled
						? 'block'
						: 'none';
				} );
			};

			// Apply initially.
			applyBlockGuard();

			// Set up observer for DOM changes.
			const observer = new MutationObserver( applyBlockGuard );
			observer.observe( global.document.body, {
				childList: true,
				subtree: true,
			} );

			return () => {
				observer.disconnect();

				// Clean up overlays using the same state key targeting logic.
				const editorDoc = getEditorDocument();
				const allBlocks = Array.from(
					editorDoc.querySelectorAll( `[data-type="${ name }"]` ),
				);
				const targetElements = [];

				// Filter blocks to only include those with the same state key.
				allBlocks.forEach( ( blockElement ) => {
					const blockId = blockElement.id?.replace( 'block-', '' );
					if ( blockId ) {
						const blockStateKey = generateBlockGuardStateKey(
							name,
							blockId,
						);
						if ( blockStateKey === stateKey ) {
							targetElements.push( blockElement );
						}
					}
				} );

				targetElements.forEach( ( blockElement ) => {
					const innerBlocks = blockElement?.querySelector(
						'.block-editor-inner-blocks',
					);
					const overlay = innerBlocks?.querySelector(
						'.gatherpress-block-guard-overlay',
					);
					if ( overlay && overlay.parentNode ) {
						overlay.parentNode.removeChild( overlay );
					}
				} );
			};
		}, [ clientId, isBlockGuardEnabled, name, stateKey ] );

		// Handle List View behavior.
		useEffect( () => {
			if ( ! clientId ) {
				return;
			}

			// Store dragover handler reference for cleanup.
			let dragoverHandler = null;

			const handleListView = () => {
				// Find the list view item.
				const listViewItem = global.document.querySelector(
					`.block-editor-list-view-leaf[data-block="${ clientId }"]`,
				);

				if ( ! listViewItem ) {
					return;
				}

				// Find the expander.
				const expander = listViewItem.querySelector(
					'.block-editor-list-view__expander',
				);

				if ( ! expander ) {
					return;
				}

				// Find the SVG inside the expander.
				const expanderSvg = expander.querySelector( 'svg' );

				if ( ! expanderSvg ) {
					return;
				}

				if ( isBlockGuardEnabled ) {
					// Make expander non-interactive but preserve layout.
					expander.style.pointerEvents = 'none';
					expander.style.opacity = '0.3';

					// Disable the parent link element.
					const parentLink = expander.closest(
						'.block-editor-list-view-block-select-button',
					);

					if ( parentLink ) {
						parentLink.setAttribute( 'aria-expanded', 'false' );
						parentLink.style.pointerEvents = 'none';

						// Re-enable just the link itself, but not the expander.
						setTimeout( () => {
							parentLink.style.pointerEvents = 'auto';
							parentLink.classList.add(
								'gatherpress-block-guard-enabled',
							);
						}, 0 );
					}

					// Add dragover prevention if not already added.
					if ( ! dragoverHandler ) {
						dragoverHandler = ( e ) => {
							const targetBlock = e.target.closest(
								`[data-block="${ clientId }"]`,
							);

							if ( ! targetBlock ) {
								return;
							}

							// Calculate position within block.
							const rect = targetBlock.getBoundingClientRect();
							const relativeY = e.clientY - rect.top;

							// 15px or 15% of height.
							const heightThreshold = Math.min(
								15,
								rect.height * 0.15,
							);

							// Only prevent events in middle section (allow edges).
							const isEdgeArea =
								relativeY < heightThreshold ||
								relativeY > rect.height - heightThreshold;

							if ( ! isEdgeArea ) {
								e.stopPropagation();
							}
						};

						// Add the event listener.
						global.document.addEventListener(
							'dragover',
							dragoverHandler,
							true,
						);
					}
				} else {
					// Restore interactivity.
					expander.style.pointerEvents = '';
					expander.style.opacity = '';

					// Re-enable the parent link.
					const parentLink = expander.closest(
						'.block-editor-list-view-block-select-button',
					);
					if ( parentLink ) {
						parentLink.style.pointerEvents = '';
						parentLink.classList.remove(
							'gatherpress-block-guard-enabled',
						);
					}

					// Remove dragover prevention.
					if ( dragoverHandler ) {
						global.document.removeEventListener(
							'dragover',
							dragoverHandler,
							true,
						);
						dragoverHandler = null;
					}
				}
			};

			setTimeout( handleListView, 100 );

			const observer = new MutationObserver( () =>
				setTimeout( handleListView, 50 ),
			);

			observer.observe( global.document.body, {
				childList: true,
				subtree: true,
			} );

			return () => {
				observer.disconnect();

				// Clean up event listener.
				if ( dragoverHandler ) {
					global.document.removeEventListener(
						'dragover',
						dragoverHandler,
						true,
					);
				}
			};
		}, [ clientId, isBlockGuardEnabled ] );

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody>
						<ToggleControl
							label={ __( 'Block Guard', 'gatherpress' ) }
							checked={ isBlockGuardEnabled }
							onChange={ ( value ) => {
								setIsBlockGuardEnabled( value );

								const expander = global.document.querySelector(
									`.block-editor-list-view-leaf[data-block="${ clientId }"][data-expanded="true"] .block-editor-list-view__expander`,
								);

								if ( value && expander ) {
									expander.click();
									expander.style.pointerEvents = 'none';
								}
							} }
							help={
								isBlockGuardEnabled
									? __(
										'Toggle to unprotect and update the block.',
										'gatherpress',
									)
									: __(
										'Block protection is disabled. Inner blocks can be freely edited.',
										'gatherpress',
									)
							}
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}, 'withBlockGuard' );

/**
 * Register the HOC as a filter for the BlockEdit component.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/
 */
addFilter( 'editor.BlockEdit', 'gatherpress/with-block-guard', withBlockGuard );

// Export functions for testing.
export { useSharedBlockGuardState, generateBlockGuardStateKey, withBlockGuard };
