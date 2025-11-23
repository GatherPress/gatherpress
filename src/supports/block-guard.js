/**
 * WordPress dependencies.
 */
import { getBlockType } from '@wordpress/blocks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { useState, useEffect, useRef } from '@wordpress/element';

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

		if ( -1 !== blockIndex ) {
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

		// Store event handlers for cleanup - use useRef to persist across renders.
		const eventHandlersRef = useRef( {} );

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

					// Use the inert attribute to disable all interactions within inner blocks.
					if ( isBlockGuardEnabled ) {
						innerBlocksContainer.inert = true;
						// Add a visual indicator that the content is protected (optional).
						innerBlocksContainer.style.opacity = '0.95';
						innerBlocksContainer.style.cursor = 'not-allowed';
						// Mark it so we can find it for cleanup.
						innerBlocksContainer.dataset.gatherPressGuarded = 'true';
					} else {
						innerBlocksContainer.inert = false;
						innerBlocksContainer.style.opacity = '';
						innerBlocksContainer.style.cursor = '';
						delete innerBlocksContainer.dataset.gatherPressGuarded;
					}

					// Handle block appender visibility.
					const blockAppender = innerBlocksContainer.querySelector(
						'.block-list-appender',
					);

					if ( blockAppender ) {
						blockAppender.style.display = isBlockGuardEnabled
							? 'none'
							: '';
					}
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

				// Clean up ALL guarded elements, regardless of how we find them.
				const editorDoc = getEditorDocument();

				// First, clean up any elements we marked as guarded.
				const guardedElements = editorDoc.querySelectorAll( '[data-gather-press-guarded="true"]' );
				guardedElements.forEach( ( element ) => {
					element.inert = false;
					element.style.opacity = '';
					element.style.cursor = '';
					delete element.dataset.gatherPressGuarded;
				} );

				// Also clean up based on state key for thoroughness.
				const allBlocks = Array.from(
					editorDoc.querySelectorAll( `[data-type="${ name }"]` ),
				);

				allBlocks.forEach( ( blockElement ) => {
					const blockId = blockElement.id?.replace( 'block-', '' );
					if ( blockId ) {
						const blockStateKey = generateBlockGuardStateKey(
							name,
							blockId,
						);
						if ( blockStateKey === stateKey ) {
							const innerBlocks = blockElement?.querySelector(
								'.block-editor-inner-blocks',
							);

							// Clean up inert attribute and styles.
							if ( innerBlocks ) {
								innerBlocks.inert = false;
								innerBlocks.style.opacity = '';
								innerBlocks.style.cursor = '';
								delete innerBlocks.dataset.gatherPressGuarded;

								// Also restore block appender.
								const blockAppender = innerBlocks.querySelector( '.block-list-appender' );
								if ( blockAppender ) {
									blockAppender.style.display = '';
								}
							}
						}
					}
				} );
			};
		}, [ clientId, isBlockGuardEnabled, name, stateKey ] );

		// Handle List View behavior.
		useEffect( () => {
			if ( ! clientId ) {
				return;
			}

			// Copy ref value to avoid react-hooks/exhaustive-deps warning.
			const currentHandlers = eventHandlersRef.current;

			// Inject CSS to prevent nesting behavior on guarded blocks.
			let styleElement = global.document.getElementById( 'gatherpress-block-guard-styles' );
			if ( ! styleElement ) {
				styleElement = global.document.createElement( 'style' );
				styleElement.id = 'gatherpress-block-guard-styles';
				styleElement.textContent = `
					/* Prevent WordPress from showing nesting state on guarded blocks */
					.gatherpress-block-guard-enabled.is-nesting .block-editor-list-view-branch {
						display: none !important;
					}
					.gatherpress-block-guard-enabled .block-editor-list-view-branch {
						display: none !important;
					}
					/* Prevent drop indicator from showing */
					.gatherpress-block-guard-enabled .block-editor-list-view-drop-indicator {
						display: none !important;
					}
					/* Prevent the nesting effect */
					.gatherpress-block-guard-enabled.is-nesting {
						background: transparent !important;
					}
					/* Dim and disable expanders on guarded blocks */
					.gatherpress-block-guard-enabled .block-editor-list-view__expander {
						opacity: 0.3 !important;
						pointer-events: none !important;
					}
					/* Prevent dragging into guarded blocks */
					.gatherpress-block-guard-enabled * {
						pointer-events: none !important;
					}
					.gatherpress-block-guard-enabled .block-editor-list-view-block-select-button {
						pointer-events: auto !important;
					}
				`;
				global.document.head.appendChild( styleElement );
			}

			// Global event handler to prevent expansion of ALL guarded blocks.
			const preventGuardedExpansion = ( e ) => {
				// Check if this is a click on a guarded block expander.
				const expander = e.target.closest( '.block-editor-list-view__expander' );
				if ( expander ) {
					const guardedBlock = expander.closest( '.gatherpress-block-guard-enabled' );
					if ( guardedBlock ) {
						e.preventDefault();
						e.stopPropagation();
						e.stopImmediatePropagation();
						return false;
					}
				}

				// For drag events, completely stop WordPress from processing events on guarded blocks.
				if ( 'dragover' === e.type || 'dragenter' === e.type || 'drop' === e.type ) {
					const guardedTarget = e.target.closest( '.gatherpress-block-guard-enabled' );
					if ( guardedTarget ) {
						// Completely prevent WordPress from seeing this event.
						e.preventDefault();
						e.stopPropagation();
						e.stopImmediatePropagation();

						// Remove nesting class if it got added.
						guardedTarget.classList.remove( 'is-nesting' );

						if ( e.dataTransfer ) {
							e.dataTransfer.dropEffect = 'none';
						}
						return false;
					}
				}
			};

			// Add global listeners for all interaction types.
			const eventTypes = [ 'click', 'dragenter', 'dragover', 'dragleave', 'drop', 'dragstart' ];
			eventTypes.forEach( ( eventType ) => {
				global.document.addEventListener( eventType, preventGuardedExpansion, true );
			} );

			const handleListView = () => {
				// Find the list view item.
				const listViewItem = global.document.querySelector(
					`.block-editor-list-view-leaf[data-block="${ clientId }"]`,
				);

				if ( ! listViewItem ) {
					return;
				}

				if ( isBlockGuardEnabled ) {
					// Visual feedback.
					const expander = listViewItem.querySelector( '.block-editor-list-view__expander' );
					if ( expander ) {
						expander.style.opacity = '0.3';
						expander.style.pointerEvents = 'none';
					}

					listViewItem.classList.add( 'gatherpress-block-guard-enabled' );

					// Create event handler that blocks all drag interactions.
					const blockDragEvents = ( e ) => {
						// Check if this event is targeting our guarded block.
						const targetGuardedBlock = e.target.closest( `[data-block="${ clientId }"]` );
						if ( targetGuardedBlock ) {
							e.preventDefault();

							// Remove any nesting classes that might get added.
							listViewItem.classList.remove( 'is-nesting' );

							if ( e.dataTransfer ) {
								e.dataTransfer.dropEffect = 'none';
							}

							return false;
						}
					};

					// Store handler reference for cleanup.
					eventHandlersRef.current[ clientId ] = blockDragEvents;

					// Add event listeners for all drag events on the list item.
					const dragEvents = [ 'dragenter', 'dragover', 'dragleave', 'drop' ];
					dragEvents.forEach( ( eventType ) => {
						listViewItem.addEventListener( eventType, blockDragEvents, true );
					} );

					// Also prevent click on expander to prevent opening.
					if ( expander ) {
						expander.addEventListener( 'click', blockDragEvents, true );
					}
				} else {
					// Restore everything.
					const expander = listViewItem.querySelector( '.block-editor-list-view__expander' );
					if ( expander ) {
						expander.style.opacity = '';
						expander.style.pointerEvents = '';
					}

					listViewItem.classList.remove( 'gatherpress-block-guard-enabled' );

					// Remove event listeners.
					const handler = currentHandlers[ clientId ];
					if ( handler ) {
						const dragEvents = [ 'dragenter', 'dragover', 'dragleave', 'drop' ];
						dragEvents.forEach( ( eventType ) => {
							listViewItem.removeEventListener( eventType, handler, true );
						} );

						const expanderElement = listViewItem.querySelector( '.block-editor-list-view__expander' );
						if ( expanderElement ) {
							expanderElement.removeEventListener( 'click', handler, true );
						}

						delete currentHandlers[ clientId ];
					}
				}
			};

			setTimeout( handleListView, 0 );

			const observer = new MutationObserver( () =>
				setTimeout( handleListView, 0 ),
			);

			observer.observe( global.document.body, {
				childList: true,
				subtree: true,
			} );

			return () => {
				observer.disconnect();

				// Remove global event listeners.
				const globalEventTypes = [ 'click', 'dragenter', 'dragover', 'dragleave', 'drop', 'dragstart' ];
				globalEventTypes.forEach( ( eventType ) => {
					global.document.removeEventListener( eventType, preventGuardedExpansion, true );
				} );

				// Clean up event handlers and styles.
				const listViewItem = global.document.querySelector(
					`.block-editor-list-view-leaf[data-block="${ clientId }"]`,
				);
				if ( listViewItem ) {
					const cleanupExpander = listViewItem.querySelector( '.block-editor-list-view__expander' );
					if ( cleanupExpander ) {
						cleanupExpander.style.opacity = '';
						cleanupExpander.style.pointerEvents = '';
					}

					listViewItem.classList.remove( 'gatherpress-block-guard-enabled' );

					// Remove event listeners.
					const handler = currentHandlers[ clientId ];
					if ( handler ) {
						const dragEvents = [ 'dragenter', 'dragover', 'dragleave', 'drop' ];
						dragEvents.forEach( ( eventType ) => {
							listViewItem.removeEventListener( eventType, handler, true );
						} );

						const expanderElement = listViewItem.querySelector( '.block-editor-list-view__expander' );
						if ( expanderElement ) {
							expanderElement.removeEventListener( 'click', handler, true );
						}

						delete currentHandlers[ clientId ];
					}
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
